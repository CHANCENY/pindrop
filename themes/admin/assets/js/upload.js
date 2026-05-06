/**
 * ChunkUploader — Vanilla JS progressive file upload enhancer
 *
 * Scans for <input type="file"> elements, reads config from a sibling <noscript>
 * containing JSON settings, then injects a full upload UI (drop zone, progress,
 * previews, errors) into the parent element. Files are uploaded in 5 MB chunks.
 *
 * Expected noscript JSON shape:
 * {
 *   "allowedTypes": ["image/png", "image/jpeg", "application/pdf"],
 *   "maxSize": 10000000,
 *   "multiple": true
 * }
 */


  "use strict";

  /* ─────────────────────────────────────────────
     Constants
  ───────────────────────────────────────────── */
  const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

  const ENDPOINTS = {
    chunk: "/internal/upload/chunk",
    session: (key) => `/internal/upload/session/${key}`,
    delete: (id) => `/internal/upload/delete/${id}`,
  };

  /* ─────────────────────────────────────────────
     Utilities
  ───────────────────────────────────────────── */

  /** Generate a short unique key for one upload batch */
  function genKey() {
    return `u_${Date.now().toString(36)}_${Math.random()
      .toString(36)
      .slice(2, 8)}`;
  }

  /** Convert an ArrayBuffer slice to a base64 string */
  function bufferToBase64(buffer) {
    let binary = "";
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  /** Read a File slice as an ArrayBuffer */
  function readSlice(file, start, end) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (e) => resolve(e.target.result);
      reader.onerror = () => reject(new Error("FileReader error"));
      reader.readAsArrayBuffer(file.slice(start, end));
    });
  }

  /** POST JSON, return parsed response */
  async function postJSON(url, body) {
    const token = await window.behaviour.createCsrfToken();
    body['_csrf_token'] = token
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const text = await res.text().catch(() => res.statusText);
      throw new Error(`HTTP ${res.status}: ${text}`);
    }
    return res.json();
  }

  /** DELETE request */
  async function sendDelete(url) {
    const res = await fetch(url, { method: "GET" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res;
  }

  /** Friendly byte formatter */
  function fmtBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
  }

  /* ─────────────────────────────────────────────
     CSS injection (once)
  ───────────────────────────────────────────── */

  function injectStyles() {
    if (document.getElementById("cu-styles")) return;
    const style = document.createElement("style");
    style.id = "cu-styles";
    style.textContent = `
      /* ── Uploader shell ── */
      .cu-shell {
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 14px;
        color: #1a1a2e;
        --cu-accent:   #4f46e5;
        --cu-accent2:  #7c3aed;
        --cu-danger:   #dc2626;
        --cu-success:  #16a34a;
        --cu-border:   #d1d5db;
        --cu-bg:       #f9fafb;
        --cu-radius:   10px;
      }

      /* ── Drop zone ── */
      .cu-drop {
        position: relative;
        border: 2px dashed var(--cu-border);
        border-radius: var(--cu-radius);
        background: var(--cu-bg);
        padding: 32px 20px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        overflow: hidden;
      }
      .cu-drop:hover,
      .cu-drop.cu-drag-over {
        border-color: var(--cu-accent);
        background: #eef2ff;
      }
      .cu-drop-icon {
        display: block;
        margin: 0 auto 12px;
        width: 44px; height: 44px;
        opacity: .55;
      }
      .cu-drop-label {
        display: block;
        font-size: 15px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
      }
      .cu-drop-hint {
        font-size: 12px;
        color: #6b7280;
      }
      .cu-drop input[type=file] {
        position: absolute;
        inset: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
      }

      /* ── File list ── */
      .cu-list {
        list-style: none;
        margin: 12px 0 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
      }
      .cu-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid var(--cu-border);
        border-radius: var(--cu-radius);
        padding: 10px 12px;
        transition: box-shadow .15s;
      }
      .cu-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }

      /* thumbnail */
      .cu-thumb {
        width: 44px; height: 44px;
        border-radius: 6px;
        object-fit: cover;
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
      }
      .cu-thumb img { width: 100%; height: 100%; object-fit: cover; }
      .cu-thumb-icon { font-size: 22px; }

      /* info */
      .cu-info { min-width: 0; }
      .cu-fname {
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
      }
      .cu-fmeta {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
      }

      /* progress bar */
      .cu-bar-wrap {
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        margin-top: 6px;
        overflow: hidden;
      }
      .cu-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--cu-accent), var(--cu-accent2));
        border-radius: 2px;
        transition: width .15s;
      }
      .cu-bar.cu-done { background: var(--cu-success); }
      .cu-bar.cu-error { background: var(--cu-danger); }

      /* status label */
      .cu-status {
        font-size: 11px;
        margin-top: 3px;
      }
      .cu-status.ok  { color: var(--cu-success); }
      .cu-status.err { color: var(--cu-danger); }
      .cu-status.uploading { color: var(--cu-accent); }

      /* remove / delete button */
      .cu-remove {
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        font-size: 18px;
        line-height: 1;
        padding: 4px 6px;
        border-radius: 6px;
        transition: color .15s, background .15s;
        flex-shrink: 0;
        align-self: flex-start;
      }
      .cu-remove:hover { color: var(--cu-danger); background: #fee2e2; }

      /* ── Error banner ── */
      .cu-errors {
        margin-top: 8px;
      }
      .cu-error-msg {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: var(--cu-radius);
        padding: 10px 12px;
        font-size: 13px;
        color: var(--cu-danger);
        margin-bottom: 6px;
        animation: cu-fadein .2s ease;
      }
      .cu-error-msg button {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--cu-danger);
        font-size: 16px;
        line-height: 1;
        padding: 0 2px;
        flex-shrink: 0;
      }

      @keyframes cu-fadein {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
  }

  /* ─────────────────────────────────────────────
     Build UI for one field
  ───────────────────────────────────────────── */

  function buildUI(originalInput, config, parent) {
    /* ── drop zone ── */
    const drop = document.createElement("div");
    drop.className = "cu-drop";
    drop.innerHTML = `
      <svg class="cu-drop-icon" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 16V8m0 0-3 3m3-3 3 3"/>
        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
      </svg>
      <span class="cu-drop-label">
        Drop files here or <u style="color:var(--cu-accent)">browse</u>
      </span>
      <span class="cu-drop-hint">
        ${config.allowedTypes.join(", ")} · max ${fmtBytes(config.maxSize)}
        ${config.multiple ? " · multiple files" : ""}
      </span>
    `;

    /* clone the original input, configure it */
    const fileInput = originalInput.cloneNode(true);
    fileInput.style.cssText = "";           // clear old styles
    fileInput.removeAttribute("id");        // avoid id duplication
    fileInput.multiple = !!config.multiple;
    if (config.allowedTypes.length) {
      fileInput.accept = config.allowedTypes.join(",");
    }
    drop.appendChild(fileInput);

    /* ── error container ── */
    const errorsWrap = document.createElement("div");
    errorsWrap.className = "cu-errors";

    /* ── file list ── */
    const list = document.createElement("ul");
    list.className = "cu-list";

    /* ── hidden field to hold comma-separated uploaded file IDs ── */
    const hiddenField = document.createElement("input");
    hiddenField.type  = "hidden";
    // Mirror the original input's name with a "_ids" suffix, or fall back to a generic name
    hiddenField.name  = originalInput.name ? `${originalInput.name}_ids` : "uploaded_file_ids";
    // If the original input had an id, derive one for the hidden field too
    if (originalInput.id) hiddenField.id = `${originalInput.id}_ids`;
    hiddenField.value = "";

    /* ── assemble ── */
    const shell = document.createElement("div");
    shell.className = "cu-shell";
    shell.appendChild(drop);
    shell.appendChild(errorsWrap);
    shell.appendChild(list);

    /* hide the original input */
    originalInput.style.display = "none";
    parent.appendChild(shell);
    parent.appendChild(hiddenField);

    return { drop, fileInput, errorsWrap, list, hiddenField };
  }

  /* ─────────────────────────────────────────────
     Error helpers
  ───────────────────────────────────────────── */

  function showError(errorsWrap, message) {
    const div = document.createElement("div");
    div.className = "cu-error-msg";
    div.innerHTML = `<span>⚠ ${escHtml(message)}</span>`;
    const btn = document.createElement("button");
    btn.textContent = "×";
    btn.setAttribute("aria-label", "Dismiss");
    btn.onclick = () => div.remove();
    div.appendChild(btn);
    errorsWrap.appendChild(div);
  }

  function escHtml(str) {
    return str.replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
    );
  }

  /* ─────────────────────────────────────────────
     Validate a file against config
  ───────────────────────────────────────────── */

  function validate(file, config) {
    if (config.allowedTypes.length && !config.allowedTypes.includes(file.type)) {
      return `"${file.name}" has unsupported type (${file.type || "unknown"}).`;
    }
    if (file.size > config.maxSize) {
      return `"${file.name}" exceeds the ${fmtBytes(config.maxSize)} size limit.`;
    }
    return null;
  }

  /* ─────────────────────────────────────────────
     Build a list item (before upload starts)
  ───────────────────────────────────────────── */

  function createListItem(file, list, errorsWrap) {
    const li = document.createElement("li");
    li.className = "cu-item";

    /* thumbnail */
    const thumb = document.createElement("div");
    thumb.className = "cu-thumb";
    if (file.type.startsWith("image/")) {
      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      img.onload = () => URL.revokeObjectURL(img.src);
      thumb.appendChild(img);
    } else {
      // generic file emoji
      const icon = document.createElement("span");
      icon.className = "cu-thumb-icon";
      icon.textContent = iconForType(file.type);
      thumb.appendChild(icon);
    }

    /* info */
    const info = document.createElement("div");
    info.className = "cu-info";
    info.innerHTML = `
      <div class="cu-fname">${escHtml(file.name)}</div>
      <div class="cu-fmeta">${fmtBytes(file.size)} · ${file.type || "unknown"}</div>
      <div class="cu-bar-wrap"><div class="cu-bar"></div></div>
      <div class="cu-status uploading">Waiting…</div>
    `;

    /* remove button (pre-upload) */
    const removeBtn = document.createElement("button");
    removeBtn.className = "cu-remove";
    removeBtn.textContent = "×";
    removeBtn.setAttribute("aria-label", "Remove");

    li.appendChild(thumb);
    li.appendChild(info);
    li.appendChild(removeBtn);
    list.appendChild(li);

    const bar    = info.querySelector(".cu-bar");
    const status = info.querySelector(".cu-status");

    return { li, bar, status, removeBtn };
  }

  function iconForType(type) {
    if (type.startsWith("video/"))       return "🎬";
    if (type.startsWith("audio/"))       return "🎵";
    if (type === "application/pdf")      return "📄";
    if (type.includes("zip") || type.includes("compressed")) return "🗜️";
    if (type.includes("word"))           return "📝";
    if (type.includes("spreadsheet") || type.includes("excel")) return "📊";
    return "📎";
  }

  /* ─────────────────────────────────────────────
     Core: upload one file in chunks
  ───────────────────────────────────────────── */

  async function uploadFile(file, { bar, status, removeBtn, li }, errorsWrap, config, list, hiddenField) {
    const uploadKey = genKey();
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

    status.textContent = "Uploading…";
    status.className = "cu-status uploading";

    try {
      for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end   = Math.min(start + CHUNK_SIZE, file.size);

        const buffer = await readSlice(file, start, end);
        const data   = bufferToBase64(buffer);

        let position;
        if (totalChunks === 1)     position = "start+end"; // single-chunk = treat as start+end
        else if (i === 0)          position = "start";
        else if (i === totalChunks - 1) position = "end";
        else                       position = "middle";

        await postJSON(ENDPOINTS.chunk, { data, position, uploadKey, config, name: file.name });

        /* progress */
        const pct = Math.round(((i + 1) / totalChunks) * 100);
        bar.style.width = `${pct}%`;
        status.textContent = `Uploading… ${pct}%`;
      }

      /* finalise */
      status.textContent = "Processing…";
      const files = await fetch(ENDPOINTS.session(uploadKey)).then((r) => {
        if (!r.ok) throw new Error(`Session error ${r.status}`);
        return r.json();
      });

      bar.style.width = "100%";
      bar.classList.add("cu-done");
      status.textContent = "Upload complete ✓";
      status.className = "cu-status ok";

      /* bind delete button with server id */
      if (Array.isArray(files) && files.length > 0) {
        const serverFile = files[0]; // one file per upload batch
        li.dataset.fid = serverFile.id;
        syncHiddenField(list, hiddenField);

        removeBtn.onclick = async (e) => {
          e.preventDefault()
          try {
            await sendDelete(ENDPOINTS.delete(serverFile.id));
            li.remove();
             syncHiddenField(list, hiddenField);
          } catch (e) {
            showError(errorsWrap, `Could not delete "${serverFile.name}": ${e.message}`);
          }
        };
      }
    } catch (err) {
      bar.style.width = "100%";
      bar.classList.add("cu-error");
      status.textContent = `Error: ${err.message}`;
      status.className = "cu-status err";
      showError(errorsWrap, `Upload failed for "${file.name}": ${err.message}`);
      /* allow user to remove the failed item */
      removeBtn.onclick = () => li.remove();
    }
  }

  /* ─────────────────────────────────────────────
     Wire events for one uploader instance
  ───────────────────────────────────────────── */

  function wireUploader({ drop, fileInput, errorsWrap, list, hiddenField }, config) {

    function handleFiles(files) {
      for (const file of Array.from(files)) {
        /* validate */
        const err = validate(file, config);
        if (err) { showError(errorsWrap, err); continue; }

        /* build UI row */
        const ui = createListItem(file, list, errorsWrap);

        /* allow cancel before upload using remove btn */
        let cancelled = false;
        ui.removeBtn.onclick = () => { cancelled = true; ui.li.remove(); };

        /* start upload */
        uploadFile(file, ui, errorsWrap, config,list, hiddenField);
      }
    }

    /* click-to-browse handled by the hidden <input> inside drop */
    fileInput.addEventListener("change", (e) => {
      handleFiles(e.target.files);
      fileInput.value = ""; // reset so same file can be re-added
    });

    /* drag & drop */
    drop.addEventListener("dragover", (e) => {
      e.preventDefault();
      drop.classList.add("cu-drag-over");
    });
    ["dragleave", "dragend"].forEach((ev) =>
      drop.addEventListener(ev, () => drop.classList.remove("cu-drag-over"))
    );
    drop.addEventListener("drop", (e) => {
      e.preventDefault();
      drop.classList.remove("cu-drag-over");
      if (e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });
  }

  /* ─────────────────────────────────────────────
     Parse noscript config
  ───────────────────────────────────────────── */

  function parseConfig(parent) {
    const noscript = parent.querySelector("noscript");
    const defaults = { allowedTypes: [], maxSize: 10 * 1024 * 1024, multiple: false };
    if (!noscript) return defaults;

    try {
      const raw = noscript.textContent.trim();
      const parsed = JSON.parse(raw);
      return {
        allowedTypes: Array.isArray(parsed.allowedTypes) ? parsed.allowedTypes : defaults.allowedTypes,
        maxSize:      typeof parsed.maxSize === "number"  ? parsed.maxSize      : defaults.maxSize,
        multiple:     typeof parsed.multiple === "boolean"? parsed.multiple     : defaults.multiple,
      };
    } catch (e) {
      console.warn("[ChunkUploader] Could not parse noscript config:", e);
      return defaults;
    }
  }

  /* ─────────────────────────────────────────────
     Sync hidden field
     Reads all data-fid values from successfully
     uploaded list items and writes them as a
     comma-separated string into the hidden input.
  ───────────────────────────────────────────── */

  function syncHiddenField(list, hiddenField) {
    const ids = Array.from(list.querySelectorAll(".cu-item[data-fid]"))
      .map((li) => li.dataset.fid)
      .filter(Boolean);
    hiddenField.value = ids.join(",");
  }

  /* ─────────────────────────────────────────────
     Entry point — scan DOM
  ───────────────────────────────────────────── */

  export function initFileInputs() {
    injectStyles();

    const fileInputs = document.querySelectorAll('input[type="file"]');
    if (!fileInputs.length) return;

    fileInputs.forEach((input) => {
      const parent = input.parentElement;
      if (!parent) return;

      /* avoid double-init */
      if (parent.dataset.cuInit) return;
      parent.dataset.cuInit = "1";

      const config = parseConfig(parent);
      const ui     = buildUI(input, config, parent);
      wireUploader(ui, config);
    });
  }
