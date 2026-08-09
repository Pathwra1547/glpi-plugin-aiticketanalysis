# MCP mode: read-only GLPI tools for AnythingLLM

**English version** · [Русская версия](mcp-glpi.ru.md) · [back to README](../README.md)

This guide explains how to give the AnythingLLM agent the ability to look things up in GLPI while
it analyses a ticket, and how to do it without handing anyone the keys to your ITSM system.

---

## Table of contents

1. [What this is, and what it is not](#1-what-this-is-and-what-it-is-not)
2. [How it fits together](#2-how-it-fits-together)
3. [Step 1 — enable the GLPI REST API and create tokens](#3-step-1--enable-the-glpi-rest-api-and-create-tokens)
4. [Step 2 — install the MCP server inside the AnythingLLM container](#4-step-2--install-the-mcp-server-inside-the-anythingllm-container)
5. [Step 3 — write the MCP configuration file](#5-step-3--write-the-mcp-configuration-file)
6. [Step 4 — deploy and restart](#6-step-4--deploy-and-restart)
7. [Step 5 — verify that MCP came up and the agent sees the tools](#7-step-5--verify-that-mcp-came-up-and-the-agent-sees-the-tools)
8. [Step 6 — enable agent mode in the plugin](#8-step-6--enable-agent-mode-in-the-plugin)
9. [Security notes specific to MCP](#9-security-notes-specific-to-mcp)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. What this is, and what it is not

The MCP server is **not part of this project**. It is a third-party npm package:

| | |
| --- | --- |
| **Project** | [`mcp-glpi` by GMS64260](https://github.com/GMS64260/mcp-glpi) |
| **License** | MIT |
| **Version referenced here** | 3.2.0 |
| **npm package** | `mcp-glpi` |

All credit for the MCP server goes to its authors. This repository ships only:

- this guide,
- a placeholder configuration file:
  [`anythingllm_mcp_servers.example.json`](mcp/anythingllm_mcp_servers.example.json),
- an example workspace prompt tuned for agent runs:
  [`workspace-prompt-mcp.example.txt`](workspace-prompt-mcp.example.txt).

If you redistribute the installed package, keep its bundled `LICENSE` and `README.md` intact — MIT
permits redistribution but requires attribution.

> **Naming warning.** The plugin's `.gitignore` deliberately excludes any file called
> `anythingllm_mcp_servers.json`, because the real one contains GLPI tokens. Keep the real file out
> of version control; only the `.example.json` placeholder belongs in the repository.

## 2. How it fits together

```
GLPI ticket page
   └── "AI L3" button  ──POST──▶  ajax/analyze.php
                                     │  builds JSON context
                                     ▼
                        AnythingLLM  /api/v1/workspace/<slug>/chat
                        message = "@agent" + context
                                     │
                        agent decides it needs more data
                                     ▼
                        MCP server "glpi-readonly"  (node process
                        started by AnythingLLM inside its container)
                                     │  GLPI REST API, read-only tokens
                                     ▼
                                   GLPI
```

The MCP server runs **inside the AnythingLLM container**, started by AnythingLLM itself as a child
process. It is not a separate container and it does not need its own port, network or compose
service. AnythingLLM reads its MCP definitions from a single file in its storage directory and
launches each configured server on demand (or at start-up, if `autoStart` is set).

## 3. Step 1 — enable the GLPI REST API and create tokens

You need two tokens: an **App-Token** identifying the client application, and a **user token**
identifying the account the calls run as.

### 3.1. Enable the REST API

**Setup → General → API**. Enable the REST API.

### 3.2. Create an API client and get the App-Token

On the same page, create an **API client**. Give it a recognisable name (for example
`anythingllm-mcp`) and save it — GLPI generates the **App-Token** for it.

While you are here, restrict the client by **IP range** to the AnythingLLM host or container
network. This is cheap to do and materially reduces the value of a stolen token.

### 3.3. Create a dedicated read-only account and get its user token

Do **not** use a personal administrator account.

1. Create a profile with **read** rights only — on tickets and on whatever object types you want the
   model to be able to look up. Explicitly deny write, delete, purge and administration rights.
2. Create a user, e.g. `svc-mcp-readonly`, assign that profile, and scope it to the entities it
   should see.
3. Log in as (or edit) that user and generate the **API token** under
   **User → Settings → Remote access keys**.

Why this matters is covered in [section 9](#9-security-notes-specific-to-mcp) — in short, the MCP
package exposes write tools too, and only GLPI permissions actually stop them.

## 4. Step 2 — install the MCP server inside the AnythingLLM container

Install the npm package into the AnythingLLM **storage plugins** directory, so it survives container
restarts and lives next to the MCP configuration file.

The reference layout used throughout this guide is:

```
/app/server/storage/plugins/
├── anythingllm_mcp_servers.json          ← the MCP configuration
└── mcp-glpi-runtime/
    └── node_modules/
        └── mcp-glpi/
            ├── dist/index.js             ← the entry point launched by AnythingLLM
            ├── package.json
            ├── LICENSE
            └── README.md
```

Install it directly in the container (replace `anythingllm` with your container name):

```bash
docker exec -it anythingllm sh -lc '
  mkdir -p /app/server/storage/plugins/mcp-glpi-runtime &&
  cd /app/server/storage/plugins/mcp-glpi-runtime &&
  npm install --omit=dev mcp-glpi
'
```

If the container has no network access to the npm registry, build the directory on a machine that
does and copy it in:

```bash
mkdir -p mcp-glpi-runtime && cd mcp-glpi-runtime
npm install --omit=dev mcp-glpi
cd ..
docker cp mcp-glpi-runtime anythingllm:/app/server/storage/plugins/mcp-glpi-runtime
```

**Use this vendored layout rather than `npx -y mcp-glpi`.** An `npx` command re-resolves the package
from the npm registry on every start; on a host without registry access, the MCP server then fails
with a connection timeout at start-up, every single time.

Check that the container's Node.js is recent enough — the reference stand runs Node 18:

```bash
docker exec anythingllm node --version
```

## 5. Step 3 — write the MCP configuration file

AnythingLLM reads all MCP server definitions from
`/app/server/storage/plugins/anythingllm_mcp_servers.json`.

The template is in this repository:
[`docs/mcp/anythingllm_mcp_servers.example.json`](mcp/anythingllm_mcp_servers.example.json).

```json
{
  "mcpServers": {
    "glpi-readonly": {
      "command": "node",
      "args": [
        "/app/server/storage/plugins/mcp-glpi-runtime/node_modules/mcp-glpi/dist/index.js"
      ],
      "env": {
        "GLPI_URL": "http://your-glpi-host",
        "GLPI_APP_TOKEN": "YOUR_GLPI_APP_TOKEN",
        "GLPI_USER_TOKEN": "YOUR_GLPI_READONLY_USER_TOKEN"
      },
      "anythingllm": {
        "autoStart": true
      }
    }
  }
}
```

| Key | Meaning |
| --- | --- |
| `glpi-readonly` | The server name shown in the AnythingLLM UI. Name it after what it is allowed to do. |
| `command` / `args` | Launch `node` against the vendored entry point. No registry access needed at start-up. |
| `GLPI_URL` | GLPI **as seen from inside the AnythingLLM container** — `http://glpi` on a shared Docker network, `http://host.docker.internal` when GLPI runs on the host, or a LAN address. Not `localhost`: inside the container that is AnythingLLM itself. |
| `GLPI_APP_TOKEN` | The App-Token from step 3.2. |
| `GLPI_USER_TOKEN` | The read-only user's API token from step 3.3. |
| `anythingllm.autoStart` | Start the server with AnythingLLM instead of on first use. |

**Every token in this repository is a placeholder.** Substitute real values only in the deployed
copy, never in a file you commit.

## 6. Step 4 — deploy and restart

```bash
docker cp anythingllm_mcp_servers.json \
  anythingllm:/app/server/storage/plugins/anythingllm_mcp_servers.json
docker restart anythingllm
```

If you prefer not to restart the whole container, AnythingLLM can reload MCP servers from the UI —
see the next step.

## 7. Step 5 — verify that MCP came up and the agent sees the tools

**Check the files are where the config says they are:**

```bash
docker exec anythingllm ls -la /app/server/storage/plugins/
docker exec anythingllm ls /app/server/storage/plugins/mcp-glpi-runtime/node_modules/mcp-glpi/dist/index.js
```

Both must exist. A typo in the path is the most common cause of a server that never starts.

**Check the process is running** after AnythingLLM has started the server:

```bash
docker exec anythingllm sh -lc "ps ax | grep -i mcp-glpi | grep -v grep"
```

**Check the UI.** In AnythingLLM, open **Agent Skills → MCP Servers**. The `glpi-readonly` server
should be listed and running, with its tool list expandable. Use the refresh/start control there if
it is stopped. If the server is listed but shows an error, the message usually names the cause
directly (bad path, unreachable `GLPI_URL`, rejected tokens).

**Check the agent actually uses it.** In a workspace chat, run a deliberate probe:

```
@agent look up ticket 1 in GLPI and tell me its title and status
```

If the agent answers with real data from your GLPI, the chain works end to end. If it answers that
it has no such tool, the MCP server is not attached to that workspace's agent — recheck the Agent
Skills page.

## 8. Step 6 — enable agent mode in the plugin

On the plugin configuration page (**Setup → Plugins → AI Ticket Analysis → configure**), block
**«Настройки AnythingLLM»**, set **«Использовать @agent + MCP GLPI»** to **Yes**.

What this changes: the plugin prefixes its message with `@agent`, which is how AnythingLLM decides
to run the request through its agent loop rather than a plain chat completion.

Two behaviours to be aware of:

- **Lite mode wins.** If `lite_mode` is enabled, agent mode is ignored entirely — lite mode exists
  for weak hardware, and agent runs are the opposite of that.
- **Raise your timeout.** Agent runs make several model calls per analysis and routinely take
  minutes on local models. Raise **«Таймаут запроса (сек)»** accordingly (the plugin allows up to
  600 seconds), and raise the read timeout of any reverse proxy in front of GLPI.

For agent runs, consider switching the workspace system prompt to
[`workspace-prompt-mcp.example.txt`](workspace-prompt-mcp.example.txt), a shorter seven-section
variant that leaves the model room to spend tokens on tool calls.

## 9. Security notes specific to MCP

**The `mcp-glpi` package is not a read-only server.** Alongside lookups such as `glpi_get_ticket`,
`glpi_get_ticket_timeline`, `glpi_list_tickets`, `glpi_get_user` and `glpi_get_knowbase_item`, it
also exposes writing tools — `glpi_create_ticket`, `glpi_add_followup`, `glpi_add_solution`,
`glpi_assign_ticket`, `glpi_create_user`, `glpi_delete_ticket` and others.

That is not a flaw in the package; it is a general-purpose GLPI integration. But it means the
"read-only" property of this setup comes **entirely** from the GLPI profile behind
`GLPI_USER_TOKEN`. If that token has write rights, the write tools work — and the only thing
standing between a maliciously crafted ticket and a modified GLPI is a sentence in a prompt asking
the model to behave. Prompts are not a security boundary.

Therefore:

1. **The token must belong to a profile with read rights only.** Verify by trying a write call with
   it and confirming GLPI refuses.
2. **Restrict the API client by IP** (step 3.2).
3. **Keep the real `anythingllm_mcp_servers.json` out of version control.** It contains two tokens
   in plaintext. Note that anyone with shell access to the AnythingLLM container can read it — treat
   container access as equivalent to token access.
4. **Rotate both tokens on a schedule.**
5. **Remember the injection path.** Ticket text and OCR'd attachment text reach the agent, and the
   agent has tools. A read-only toolset makes the worst case "a wrong answer"; a writable toolset
   makes the worst case "an attacker operating your ITSM through your assistant". This is discussed
   in more depth in the [security section of the README](../README.md#9-security).

## 10. Troubleshooting

### Connection timeout at start-up

Usually a configuration that launches the server via `npx -y mcp-glpi`: it contacts the npm registry
on every start, and on a host without registry access that always fails. Use the vendored `node`
form from [step 3](#5-step-3--write-the-mcp-configuration-file).

### Still timing out after switching to `node`

Then npm was not the problem and the network path to GLPI is. Test from inside the container:

```bash
docker exec anythingllm sh -lc 'wget -S -O /dev/null "$GLPI_URL/apirest.php" 2>&1 | head -20'
```

or simply try the URL you put in `GLPI_URL`. Remember that `localhost` inside the AnythingLLM
container means AnythingLLM, not GLPI.

### The server starts but every call is rejected

Token or API configuration problem, in this order of likelihood: the REST API is not enabled in
GLPI; the App-Token belongs to an API client restricted to a different IP range; the user token was
regenerated; the user's profile cannot see the entity the object belongs to. GLPI's API returns a
descriptive error — read it in the AnythingLLM agent log.

### The agent ignores the tools

Check that the MCP server is enabled for that specific workspace's agent under **Agent Skills**, and
that the plugin is actually sending `@agent` (i.e. `use_agent_mcp` is on and `lite_mode` is off).

### `@agent` runs are very slow

Expected with local models: the agent makes several model calls per run, and each one competes for
the same VRAM as the vision model. This is precisely why the plugin ships with `use_agent_mcp`
disabled by default. If you need agent mode routinely, budget more VRAM or a faster model rather
than raising timeouts indefinitely.
