/*
 * -------------------------------------------------------------------------
 * AI Ticket Analysis — plugin frontend script
 *
 * Copyright (C) 2026 AI Ticket Analysis contributors
 *
 * This file is part of the AI Ticket Analysis plugin for GLPI.
 * It is free software: you can redistribute it and/or modify it under the terms
 * of the GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 * See the LICENSE file for the full license text.
 * -------------------------------------------------------------------------
 */

(function () {
  'use strict';

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function titleFor(ticketId) {
    return ticketId ? ('AI L3 — заявка #' + ticketId) : 'AI L3';
  }

  function ensureModal(ticketId) {
    var existing = document.getElementById('aiticketanalysis-modal');
    if (existing) {
      var title = existing.querySelector('.modal-title');
      if (title) {
        title.textContent = titleFor(ticketId);
      }
      return existing;
    }

    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="modal fade" id="aiticketanalysis-modal" tabindex="-1" aria-hidden="true">' +
      '  <div class="modal-dialog modal-xl modal-dialog-scrollable">' +
      '    <div class="modal-content">' +
      '      <div class="modal-header">' +
      '        <h5 class="modal-title">' + escapeHtml(titleFor(ticketId)) + '</h5>' +
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>' +
      '      </div>' +
      '      <div id="aiticketanalysis-meta" class="aiticketanalysis-meta" hidden></div>' +
      '      <div class="modal-body">' +
      '        <div id="aiticketanalysis-status" class="alert alert-info">Подготовка анализа…</div>' +
      '        <div id="aiticketanalysis-result" class="aiticketanalysis-markdown"></div>' +
      '      </div>' +
      '      <div class="modal-footer">' +
      '        <button type="button" class="btn btn-ai-copy" id="aiticketanalysis-copy">Копировать</button>' +
      '        <button type="button" class="btn btn-ai-close" data-bs-dismiss="modal">Закрыть</button>' +
      '      </div>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(wrap.firstElementChild);

    var copyBtn = document.getElementById('aiticketanalysis-copy');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var result = document.getElementById('aiticketanalysis-result');
        var text = result ? (result.innerText || result.textContent || '').trim() : '';
        if (!text) {
          return;
        }
        var done = function () {
          var prev = copyBtn.textContent;
          copyBtn.textContent = 'Скопировано';
          setTimeout(function () {
            copyBtn.textContent = prev;
          }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () {
            window.prompt('Скопируйте текст:', text);
          });
        } else {
          window.prompt('Скопируйте текст:', text);
        }
      });
    }

    return document.getElementById('aiticketanalysis-modal');
  }

  function renderMeta(data) {
    var el = document.getElementById('aiticketanalysis-meta');
    if (!el) {
      return;
    }
    var meta = (data && data.meta) || {};
    var attInfo = (data && data.attachments) || {};
    var ragCount = (data && data.sources && data.sources.length) ? data.sources.length : 0;
    var attOk = (meta.attachments != null)
      ? meta.attachments
      : (attInfo.ok != null ? attInfo.ok : (attInfo.count != null ? attInfo.count : 0));
    var attTotal = meta.attach_total != null ? meta.attach_total : (attInfo.count != null ? attInfo.count : attOk);
    var attErr = meta.attach_errors != null ? meta.attach_errors : (attInfo.errors != null ? attInfo.errors : 0);
    var attSuspect = meta.attach_suspect != null
      ? meta.attach_suspect
      : (attInfo.suspect != null ? attInfo.suspect : 0);
    var attLabel = String(attOk);
    if (attTotal != null && Number(attTotal) !== Number(attOk)) {
      attLabel = attOk + '/' + attTotal;
    }
    var flags = [];
    if (attSuspect > 0) {
      flags.push('ненадёжно: ' + attSuspect);
    }
    if (attErr > 0) {
      flags.push('ошибки: ' + attErr);
    }
    if (flags.length) {
      attLabel += ' (' + flags.join(', ') + ')';
    }
    var parts = [
      'Workspace: ' + (data.workspace || '—'),
      'Статус: ' + (meta.status || '—'),
      'Категория: ' + (meta.category || '—'),
      'Техник: ' + (meta.technician || '—'),
      'Вложения: ' + attLabel,
      'RAG: ' + ragCount
    ];
    el.innerHTML = parts.map(function (p, i) {
      return (i ? '<span class="sep">·</span>' : '') + '<span>' + escapeHtml(p) + '</span>';
    }).join('');
    el.hidden = false;
  }

  function renderAttachNotes(data) {
    var att = (data && data.attachments) || {};
    var notes = att.notes || [];
    var detail = (data && data.attachments_detail) || [];
    var hasProblem = !!att.disabled || (att.errors > 0) || (att.suspect > 0) || (att.count === 0);
    if (!hasProblem) {
      return '';
    }
    var lines = [];
    detail.forEach(function (d) {
      var quality = d.quality || '';
      var bad = d.error || !d.chars || quality === 'none' || quality === 'low';
      if (!bad) {
        return;
      }
      var line = (d.filename || '?') + ' [' + (d.method || '?') + ', ' + (d.chars || 0) + ' симв.';
      if (quality) {
        line += ', достоверность: ' + (quality === 'good' ? 'высокая' : (quality === 'low' ? 'низкая' : 'нет текста'));
      }
      if (d.via) {
        line += ', via=' + d.via;
      }
      line += ']';
      if (d.error) {
        line += ' — ' + d.error;
      }
      lines.push(line);
    });
    notes.forEach(function (n) {
      if (lines.indexOf(n) === -1) {
        lines.push(n);
      }
    });
    if (!lines.length) {
      return '';
    }
    var html = '<div class="alert alert-warning aiticketanalysis-attach-notes"><strong>Вложения / OCR:</strong><ul>';
    lines.forEach(function (n) {
      html += '<li>' + escapeHtml(n) + '</li>';
    });
    html += '</ul></div>';
    return html;
  }

  function isCalloutHeading(title) {
    var t = String(title || '').toLowerCase();
    return t.indexOf('черновик') !== -1
      || t.indexOf('ответ заявителю') !== -1
      || t.indexOf('ответ пользователю') !== -1
      || t.indexOf('draft') !== -1;
  }

  function simpleMarkdown(md) {
    var lines = String(md || '').replace(/\r\n/g, '\n').split('\n');
    var html = [];
    var listType = null;
    var inCallout = false;

    function closeList() {
      if (listType) {
        html.push('</' + listType + '>');
        listType = null;
      }
    }

    function closeCallout() {
      if (inCallout) {
        html.push('</div>');
        inCallout = false;
      }
    }

    lines.forEach(function (raw) {
      var line = raw;
      var trimmed = line.trim();

      if (!trimmed) {
        closeList();
        return;
      }

      var mdHeading = trimmed.match(/^(#{1,3})\s+(.+)$/);
      var boldNumHeading = trimmed.match(/^(\d+)\.\s+\*\*(.+?)\*\*\s*$/);
      var plainNumHeading = trimmed.match(/^(\d+)\.\s+(.{2,70})$/);
      var sectionWords = /диагноз|вложен|риск|sla|источник|истори|действи|черновик|решени|рекоменд|rag/i;
      var asHeading = false;
      var title = '';

      if (mdHeading) {
        asHeading = true;
        title = mdHeading[2].replace(/\*\*/g, '');
      } else if (boldNumHeading) {
        asHeading = true;
        title = boldNumHeading[1] + '. ' + boldNumHeading[2];
      } else if (
        plainNumHeading &&
        !/[.!?…]$/.test(plainNumHeading[2]) &&
        sectionWords.test(plainNumHeading[2])
      ) {
        asHeading = true;
        title = plainNumHeading[1] + '. ' + plainNumHeading[2].replace(/\*\*/g, '');
      }

      if (asHeading) {
        closeList();
        closeCallout();
        html.push('<h3>' + escapeHtml(title) + '</h3>');
        if (isCalloutHeading(title)) {
          html.push('<div class="ai-callout">');
          inCallout = true;
        }
        return;
      }

      var ul = trimmed.match(/^[-*]\s+(.+)$/);
      var ol = trimmed.match(/^(\d+)[.)]\s+(.+)$/);
      if (ul || ol) {
        var type = ul ? 'ul' : 'ol';
        if (listType !== type) {
          closeList();
          html.push('<' + type + '>');
          listType = type;
        }
        var item = escapeHtml((ul ? ul[1] : ol[2]));
        item = item.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        item = item.replace(/`([^`]+)`/g, '<code>$1</code>');
        html.push('<li>' + item + '</li>');
        return;
      }

      closeList();
      var para = escapeHtml(trimmed);
      para = para.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
      para = para.replace(/`([^`]+)`/g, '<code>$1</code>');
      html.push('<p>' + para + '</p>');
    });

    closeList();
    closeCallout();
    return html.join('');
  }

  function renderSources(sources) {
    if (!sources || !sources.length) {
      return '';
    }
    var html = '<h3>Источники RAG</h3><ul class="aiticketanalysis-sources">';
    sources.forEach(function (s) {
      var title = escapeHtml(s.title || 'document');
      var score = s.score != null ? ' <small class="text-muted">(score: ' + escapeHtml(String(s.score)) + ')</small>' : '';
      html += '<li><strong>' + title + '</strong>' + score + '</li>';
    });
    html += '</ul>';
    return html;
  }

  function openModal(ticketId) {
    var el = ensureModal(ticketId);
    if (!el) {
      return null;
    }
    if (window.bootstrap && bootstrap.Modal) {
      return bootstrap.Modal.getOrCreateInstance(el);
    }
    el.style.display = 'block';
    el.classList.add('show');
    return {
      show: function () {},
      hide: function () {
        el.style.display = 'none';
        el.classList.remove('show');
      }
    };
  }

  function getCsrfToken() {
    if (typeof getAjaxCsrfToken === 'function') {
      return getAjaxCsrfToken();
    }
    var meta = document.querySelector('meta[property="glpi:csrf_token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('#aiticketanalysis-btn');
    if (!btn) {
      return;
    }
    ev.preventDefault();

    var ticketId = btn.getAttribute('data-ticket-id');
    var ajaxUrl = btn.getAttribute('data-ajax-url');
    var modal = openModal(ticketId);
    var status = document.getElementById('aiticketanalysis-status');
    var result = document.getElementById('aiticketanalysis-result');
    var metaEl = document.getElementById('aiticketanalysis-meta');

    if (!ticketId || !ajaxUrl || !status || !result) {
      return;
    }

    if (metaEl) {
      metaEl.hidden = true;
      metaEl.innerHTML = '';
    }

    status.hidden = false;
    status.className = 'alert alert-info';
    status.textContent = 'Идёт анализ #' + ticketId + '… С вложениями OCR может занять 1–4 мин.';
    result.innerHTML = '';
    if (modal && modal.show) {
      modal.show();
    }

    btn.disabled = true;

    var csrf = getCsrfToken();
    var body = new URLSearchParams();
    body.set('tickets_id', ticketId);
    if (csrf) {
      body.set('_glpi_csrf_token', csrf);
    }

    var headers = {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    };
    if (csrf) {
      headers['X-Glpi-Csrf-Token'] = csrf;
    }

    fetch(ajaxUrl, {
      method: 'POST',
      headers: headers,
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try {
            data = text ? JSON.parse(text) : null;
          } catch (e) {
            var hint;
            if (r.status === 403) {
              hint = 'Ошибка CSRF/доступа (403). Обновите страницу (Ctrl+F5) и повторите.';
            } else if (r.status === 504 || r.status === 502) {
              hint = 'Таймаут шлюза (HTTP ' + r.status + '). '
                + 'Увеличьте proxy/fastcgi_read_timeout на nginx/Apache перед GLPI до ≥300с '
                + 'и таймаут плагина; модель отвечает дольше, чем ждёт прокси.';
            } else {
              hint = 'Сервер вернул не JSON (HTTP ' + r.status + ').';
            }
            throw new Error(hint);
          }
          if (!r.ok && (!data || !data.error)) {
            throw new Error('HTTP ' + r.status);
          }
          return data;
        });
      })
      .then(function (data) {
        if (!data || !data.success) {
          status.className = 'alert alert-danger';
          status.textContent = (data && data.error) ? data.error : 'Ошибка анализа';
          return;
        }
        status.hidden = true;
        status.textContent = '';
        renderMeta(data);
        result.innerHTML = renderAttachNotes(data) + simpleMarkdown(data.text) + renderSources(data.sources);
      })
      .catch(function (err) {
        status.hidden = false;
        status.className = 'alert alert-danger';
        status.textContent = 'Сбой запроса: ' + (err && err.message ? err.message : err);
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
})();
