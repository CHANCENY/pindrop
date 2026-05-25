// ── STATE ─────────────────────────────────────────────────────────
const ct = document.getElementById("ct");
const wiki = JSON.parse(document.querySelector("#wiki-meta").textContent);
let editing = false,
  insertAfter = null,
  savedSel = null,
  curModal = null,
  ctxTarget = null,
  dragSrc = null;
let cplusTarget = null;
let refs = wiki?.content?.refs ?? [];
let nextRef = 0;
// slash state
let slashAll = [],
  slashFiltered = [],
  slashSel = 0;

// ── BLOCK DEFINITIONS (slash + picker) ────────────────────────────
const BDEFS = [
  {
    id: "p",
    lbl: "Paragraph",
    desc: "Plain text",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><line x1="2" y1="4" x2="14" y2="4"/><line x1="2" y1="8" x2="11" y2="8"/><line x1="2" y1="12" x2="13" y2="12"/></svg>',
  },
  {
    id: "h1",
    lbl: "Heading 1",
    desc: "Large title",
    grp: "Text",
    ic: "<b>H1</b>",
  },
  {
    id: "h2",
    lbl: "Heading 2",
    desc: "Section heading",
    grp: "Text",
    ic: "<b>H2</b>",
  },
  {
    id: "h3",
    lbl: "Heading 3",
    desc: "Sub-section",
    grp: "Text",
    ic: '<b style="font-size:11px">H3</b>',
  },
  {
    id: "h4",
    lbl: "Heading 4",
    desc: "Minor heading",
    grp: "Text",
    ic: '<b style="font-size:10px">H4</b>',
  },
  {
    id: "quote",
    lbl: "Blockquote",
    desc: "Pull quote",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><path d="M2 7c0-2.8 1.5-4 3.5-4v2C4 5 3.5 5.8 3.5 7H5v4H2V7zm7 0c0-2.8 1.5-4 3.5-4v2c-1.5 0-2 .8-2 2H12v4H9V7z" fill="currentColor" stroke="none"/></svg>',
  },
  {
    id: "code",
    lbl: "Code Block",
    desc: "Monospace code",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><polyline points="5,4 1,8 5,12"/><polyline points="11,4 15,8 11,12"/></svg>',
  },
  {
    id: "ul",
    lbl: "Bullet List",
    desc: "Unordered list",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><circle cx="3" cy="5" r="1.3" fill="currentColor" stroke="none"/><line x1="6" y1="5" x2="14" y2="5"/><circle cx="3" cy="9" r="1.3" fill="currentColor" stroke="none"/><line x1="6" y1="9" x2="14" y2="9"/></svg>',
  },
  {
    id: "ol",
    lbl: "Numbered List",
    desc: "Ordered list",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><text x="1" y="7" font-size="6.5" fill="currentColor" stroke="none" font-family="sans-serif" font-weight="700">1.</text><line x1="7" y1="5.5" x2="14" y2="5.5"/><text x="1" y="12" font-size="6.5" fill="currentColor" stroke="none" font-family="sans-serif" font-weight="700">2.</text><line x1="7" y1="10.5" x2="14" y2="10.5"/></svg>',
  },
  {
    id: "checklist",
    lbl: "Checklist",
    desc: "Checkbox list",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="4" width="5" height="5" rx="1"/><polyline points="2,6.5 3.5,8 5.5,5.5"/><line x1="8" y1="6.5" x2="14" y2="6.5"/><rect x="1" y="10" width="5" height="5" rx="1"/><line x1="8" y1="12.5" x2="14" y2="12.5"/></svg>',
  },
  {
    id: "hr",
    lbl: "Divider",
    desc: "Horizontal rule",
    grp: "Text",
    ic: '<svg viewBox="0 0 16 16"><line x1="1" y1="8" x2="15" y2="8"/></svg>',
  },
  {
    id: "sec",
    lbl: "Collapsible Section",
    desc: "Expand/collapse",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="1" width="14" height="14" rx="2"/><polyline points="5,7 8,10 11,7"/><line x1="4" y1="5" x2="12" y2="5"/></svg>',
  },
  {
    id: "cols2",
    lbl: "2 Columns",
    desc: "Two equal columns",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="2" width="6" height="12" rx="1"/><rect x="9" y="2" width="6" height="12" rx="1"/></svg>',
  },
  {
    id: "cols3",
    lbl: "3 Columns",
    desc: "Three columns",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="4" height="10" rx="1"/><rect x="6" y="3" width="4" height="10" rx="1"/><rect x="11" y="3" width="4" height="10" rx="1"/></svg>',
  },
  {
    id: "cols13",
    lbl: "Sidebar Left",
    desc: "Narrow + wide",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="3" height="10" rx="1"/><rect x="5" y="3" width="10" height="10" rx="1"/></svg>',
  },
  {
    id: "cols31",
    lbl: "Sidebar Right",
    desc: "Wide + narrow",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="10" height="10" rx="1"/><rect x="12" y="3" width="3" height="10" rx="1"/></svg>',
  },
  {
    id: "secbox",
    lbl: "Section Box",
    desc: "Bordered container",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="1" width="14" height="14" rx="2"/><line x1="4" y1="6" x2="12" y2="6"/></svg>',
  },
  {
    id: "ctoc",
    lbl: "Contents Table",
    desc: "Auto TOC block",
    grp: "Structure",
    ic: '<svg viewBox="0 0 16 16"><line x1="2" y1="3" x2="14" y2="3"/><line x1="4" y1="7" x2="14" y2="7"/><line x1="6" y1="11" x2="14" y2="11"/><circle cx="2" cy="7" r="1" fill="currentColor" stroke="none"/><circle cx="4" cy="11" r="1" fill="currentColor" stroke="none"/></svg>',
  },
  {
    id: "img-url",
    lbl: "Image (URL)",
    desc: "Embed from web",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="14" height="10" rx="1"/><circle cx="5.5" cy="7" r="1.2"/><path d="M1 11l3.5-4 2.5 3 2-2.5 4 5"/></svg>',
  },
  {
    id: "img-up",
    lbl: "Image Upload",
    desc: "Base64 embed",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><path d="M4 13H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-2"/><path d="M8 7v6M5 10l3-3 3 3"/></svg>',
  },
  {
    id: "figure",
    lbl: "Figure + Caption",
    desc: "Image with caption",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="2" width="14" height="9" rx="1"/><line x1="8" y1="11" x2="8" y2="14"/><line x1="4" y1="14" x2="12" y2="14"/></svg>',
  },
  {
    id: "video",
    lbl: "Video Embed",
    desc: "YouTube / Vimeo",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="14" height="10" rx="1"/><polygon points="6,6 11,8 6,10" fill="currentColor" stroke="none"/></svg>',
  },
  {
    id: "audio",
    lbl: "Audio",
    desc: "Player or embed",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6"/><polygon points="6,5.5 6,10.5 11,8" fill="currentColor" stroke="none"/></svg>',
  },
  {
    id: "mg2",
    lbl: "Media Grid 2",
    desc: "2-col gallery",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>',
  },
  {
    id: "mg3",
    lbl: "Media Grid 3",
    desc: "3-col gallery",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="4" width="4" height="8" rx="1"/><rect x="6" y="4" width="4" height="8" rx="1"/><rect x="11" y="4" width="4" height="8" rx="1"/></svg>',
  },
  {
    id: "file-up",
    lbl: "File Attachment",
    desc: "Upload any file",
    grp: "Media",
    ic: '<svg viewBox="0 0 16 16"><path d="M9 2H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6L9 2z"/><polyline points="9,2 9,6 13,6"/></svg>',
  },
  {
    id: "table",
    lbl: "Table",
    desc: "Grid picker",
    grp: "Tables",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="2" width="14" height="12" rx="1"/><line x1="1" y1="7" x2="15" y2="7"/><line x1="6" y1="2" x2="6" y2="14"/><line x1="11" y1="2" x2="11" y2="14"/></svg>',
  },
  {
    id: "dtable",
    lbl: "Definition Table",
    desc: "Key-value layout",
    grp: "Tables",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="2" width="14" height="12" rx="1"/><line x1="7" y1="2" x2="7" y2="14"/><line x1="1" y1="7" x2="15" y2="7"/></svg>',
  },
  {
    id: "form",
    lbl: "Form",
    desc: "Contact form",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="14" height="10" rx="1"/><line x1="4" y1="7" x2="12" y2="7"/><line x1="4" y1="10" x2="8" y2="10"/></svg>',
  },
  {
    id: "f-text",
    lbl: "Text Input",
    desc: "Single line field",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="5" width="14" height="6" rx="1"/><line x1="3" y1="8" x2="6" y2="8"/></svg>',
  },
  {
    id: "f-area",
    lbl: "Text Area",
    desc: "Multi-line field",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="3" width="14" height="10" rx="1"/><line x1="3" y1="7" x2="10" y2="7"/><line x1="3" y1="10" x2="7" y2="10"/></svg>',
  },
  {
    id: "f-sel",
    lbl: "Dropdown",
    desc: "Select field",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="5" width="14" height="6" rx="1"/><polyline points="10,7.5 12,9.5 14,7.5"/></svg>',
  },
  {
    id: "f-chk",
    lbl: "Checkbox Group",
    desc: "Multiple checkboxes",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="1" y="4" width="5" height="5" rx="1"/><polyline points="2,6.5 3.5,8 5.5,5.5"/><line x1="8" y1="6.5" x2="14" y2="6.5"/></svg>',
  },
  {
    id: "f-rad",
    lbl: "Radio Group",
    desc: "Single choice",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><circle cx="3.5" cy="6.5" r="2"/><circle cx="3.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/><line x1="7" y1="6.5" x2="14" y2="6.5"/></svg>',
  },
  {
    id: "f-sub",
    lbl: "Submit Button",
    desc: "Form submit",
    grp: "Forms",
    ic: '<svg viewBox="0 0 16 16"><rect x="2" y="5" width="12" height="6" rx="2"/><line x1="6" y1="8" x2="10" y2="8"/></svg>',
  },
  {
    id: "ci",
    lbl: "Info Callout",
    desc: "Blue info box",
    grp: "Callouts",
    ic: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="7"/><line x1="8" y1="7" x2="8" y2="11"/><circle cx="8" cy="5" r=".5" fill="currentColor" stroke="none"/></svg>',
  },
  {
    id: "cw",
    lbl: "Warning",
    desc: "Amber warning",
    grp: "Callouts",
    ic: '<svg viewBox="0 0 16 16"><path d="M8 2L15 14H1L8 2z"/><line x1="8" y1="7" x2="8" y2="10"/></svg>',
  },
  {
    id: "cs",
    lbl: "Success",
    desc: "Green tip box",
    grp: "Callouts",
    ic: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="7"/><polyline points="5,8 7,10 11,6"/></svg>',
  },
  {
    id: "cd",
    lbl: "Danger",
    desc: "Red alert",
    grp: "Callouts",
    ic: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="7"/><line x1="6" y1="6" x2="10" y2="10"/><line x1="10" y1="6" x2="6" y2="10"/></svg>',
  },
  {
    id: "refs",
    lbl: "References Block",
    desc: "Reference list",
    grp: "References",
    ic: '<svg viewBox="0 0 16 16"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V9"/><polyline points="10,2 14,2 14,6"/></svg>',
  },
  {
    id: "footnote",
    lbl: "Footnote",
    desc: "Insert [n] marker",
    grp: "References",
    ic: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6"/><text x="5" y="12" font-size="8" fill="currentColor" stroke="none" font-family="sans-serif">fn</text></svg>',
  },
  {
    id: "link",
    lbl: "Link",
    desc: "Hyperlink",
    grp: "Inline",
    ic: '<svg viewBox="0 0 16 16"><path d="M7 9a3.5 3.5 0 0 0 5 0l2-2a3.5 3.5 0 0 0-5-5l-1 1"/><path d="M9 7a3.5 3.5 0 0 0-5 0L2 9a3.5 3.5 0 0 0 5 5l1-1"/></svg>',
  },
  {
    id: "html",
    lbl: "HTML Snippet",
    desc: "Raw HTML",
    grp: "Advanced",
    ic: '<svg viewBox="0 0 16 16"><polyline points="5,4 1,8 5,12"/><polyline points="11,4 15,8 11,12"/><line x1="9" y1="3" x2="7" y2="13"/></svg>',
  },
];
slashAll = BDEFS;

// ── CALLOUT STYLES ────────────────────────────────────────────────
const COS = {
  ci: "background:#eff6ff;border-left:4px solid #1a56db;color:#1e3a5f",
  cw: "background:#fffbeb;border-left:4px solid #d97706;color:#78350f",
  cs: "background:#f0fdf4;border-left:4px solid #16a34a;color:#14532d",
  cd: "background:#fff1f2;border-left:4px solid #e11d48;color:#881337",
};
const COL = { ci: "Info", cw: "Warning", cs: "Success", cd: "Danger" };

// ── BUILD BLOCK HTML ──────────────────────────────────────────────
function buildBlockHTML(id) {
  if (id === "p") return "<p>New paragraph. Click to edit.</p>";
  if (id === "h1") return '<h1 id="h-' + Date.now() + '">Heading 1</h1>';
  if (id === "h2") return '<h2 id="h-' + Date.now() + '">Heading 2</h2>';
  if (id === "h3") return '<h3 id="h-' + Date.now() + '">Heading 3</h3>';
  if (id === "h4") return '<h4 id="h-' + Date.now() + '">Heading 4</h4>';
  if (id === "quote") return "<blockquote>Your quote here.</blockquote>";
  if (id === "code") return "<pre><code>// code here</code></pre>";
  if (id === "ul")
    return "<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>";
  if (id === "ol")
    return "<ol><li>First item</li><li>Second item</li><li>Third item</li></ol>";
  if (id === "checklist") return buildChecklist();
  if (id === "hr") return "<hr>";
  if (id === "sec")
    return '<div class="sec open"><div class="sec-hd"><span class="sec-arrow">▶</span><span>Section Title</span></div><div class="sec-body"><p>Section content here.</p></div></div>';
  if (id === "cols2")
    return '<div class="cols c2"><div class="cc"><p>Left column</p></div><div class="cc"><p>Right column</p></div></div>';
  if (id === "cols3")
    return '<div class="cols c3"><div class="cc"><p>Column 1</p></div><div class="cc"><p>Column 2</p></div><div class="cc"><p>Column 3</p></div></div>';
  if (id === "cols13")
    return '<div class="cols c13"><div class="cc"><p>Sidebar</p></div><div class="cc"><p>Main content area</p></div></div>';
  if (id === "cols31")
    return '<div class="cols c31"><div class="cc"><p>Main content area</p></div><div class="cc"><p>Sidebar</p></div></div>';
  if (id === "secbox")
    return '<div style="border:1px solid var(--rule);border-radius:8px;padding:22px 26px;margin:.8em 0"><h3 style="margin-top:0">Section Title</h3><p>Content here.</p></div>';
  if (id === "ctoc") return buildCTOCHTML();
  if (COS[id])
    return `<div class="co" style="${COS[id]}"><strong>${COL[id]}</strong><p>Callout content here.</p></div>`;
  if (id === "refs") return buildRefsHTML();
  return null;
}
function buildChecklist() {
  return (
    `<ul style="list-style:none;margin-left:0">` +
    ["Item one", "Item two", "Item three"]
      .map(
        (t) =>
          `<li style="display:flex;align-items:center;gap:8px;margin:.3em 0"><input type="checkbox" style="width:16px;height:16px;accent-color:var(--acc);flex-shrink:0"><span>${t}</span></li>`,
      )
      .join("") +
    "</ul>"
  );
}
function buildRefsHTML() {
  return (
    '<div class="refs" id="refs-block"><ol>' +
    refs
      .map((r) => `<li id="ref-${r.id}"><b>[${r.id}]</b> ${r.text}</li>`)
      .join("") +
    "</ol></div>"
  );
}
function buildCTOCHTML() {
  const heads = ct.querySelectorAll("h1,h2,h3");
  let items = "";
  heads.forEach((h) => {
    const tag = h.tagName.toLowerCase();
    const ml =
      tag === "h2"
        ? "margin-left:12px"
        : tag === "h3"
          ? "margin-left:24px"
          : "";
    items += `<li style="${ml}"><a href="#${h.id || ""}">${h.textContent.trim()}</a></li>`;
  });
  return `<div class="ctoc"><strong>Contents</strong><ol>${items}</ol></div>`;
}

// ── MAKE WRAP + SETUP ─────────────────────────────────────────────
function mkWrap(inner) {
  const d = document.createElement("div");
  d.className = "bw";
  d.innerHTML = inner;
  if (editing) {
    setupEditable(d);
    addControls(d);
  }
  return d;
}
function setupEditable(wrap) {
  wrap
    .querySelectorAll("p,h1,h2,h3,h4,h5,h6,blockquote,pre,li,figcaption")
    .forEach((el) => el.setAttribute("contenteditable", "true"));
  wrap
    .querySelectorAll("td,th")
    .forEach((el) => el.setAttribute("contenteditable", "true"));
  wrap
    .querySelectorAll("ul,ol,table,thead,tbody,tr")
    .forEach((el) => el.removeAttribute("contenteditable"));
  wrap
    .querySelectorAll(
      ".co strong,.co p,.cc p,.cc h3,.sec-hd span:last-child,.sec-body p",
    )
    .forEach((el) => el.setAttribute("contenteditable", "true"));
}
function addControls(wrap) {
  if (wrap.querySelector(".bctrl")) return;
  const ctrl = document.createElement("div");
  ctrl.className = "bctrl";
  ctrl.innerHTML = `<button class="mvbtn" title="Drag to reorder" draggable="true">⠿</button><button class="abtn" title="Add block">+</button><button class="delbtn" title="Delete">×</button>`;
  ctrl.querySelector(".abtn").onclick = (e) => {
    e.stopPropagation();
    insertAfter = wrap;
    openIbar();
  };
  ctrl.querySelector(".delbtn").onclick = (e) => {
    e.stopPropagation();
    if (confirm("Delete?")) {
      wrap.remove();
      rebuildTOC();
      updateWC();
    }
  };
  const mv = ctrl.querySelector(".mvbtn");
  mv.addEventListener("dragstart", (e) => {
    dragSrc = wrap;
    wrap.style.opacity = ".4";
    e.dataTransfer.effectAllowed = "move";
  });
  mv.addEventListener("dragend", () => {
    dragSrc = null;
    wrap.style.opacity = "";
    ct.querySelectorAll(".bw").forEach((b) => b.classList.remove("drag-over"));
  });
  wrap.addEventListener("dragover", (e) => {
    e.preventDefault();
    if (dragSrc && dragSrc !== wrap) wrap.classList.add("drag-over");
  });
  wrap.addEventListener("dragleave", () => wrap.classList.remove("drag-over"));
  wrap.addEventListener("drop", (e) => {
    e.preventDefault();
    wrap.classList.remove("drag-over");
    if (dragSrc && dragSrc !== wrap) wrap.before(dragSrc);
  });
  wrap.appendChild(ctrl);
}
function placeBlock(wrap) {
  if (insertAfter && insertAfter.parentNode === ct) insertAfter.after(wrap);
  else ct.appendChild(wrap);
  rebuildTOC();
  updateWC();
}

// ── INSERT BLOCK ──────────────────────────────────────────────────
const MODAL_IDS = [
  "img-url",
  "img-up",
  "figure",
  "video",
  "audio",
  "link",
  "table",
  "dtable",
  "form",
  "f-text",
  "f-area",
  "f-sel",
  "f-chk",
  "f-rad",
  "f-sub",
  "mg2",
  "mg3",
  "file-up",
  "html",
];
function insertBlock(id) {
  closeIbar();
  closeSlash();
  if (id === "footnote") {
    addFootnote();
    return;
  }
  if (MODAL_IDS.includes(id)) {
    openModal(id);
    return;
  }
  const html = buildBlockHTML(id);
  if (!html) {
    toast("Unknown block: " + id);
    return;
  }
  const w = mkWrap(html);
  placeBlock(w);
  const ed = w.querySelector('[contenteditable="true"]');
  if (ed) {
    ed.focus();
    placeEnd(ed);
  }
  toast((BDEFS.find((b) => b.id === id)?.lbl || id) + " added");
}

// ── ENTER / EXIT EDIT ─────────────────────────────────────────────
function enterEdit() {
  editing = true;
  document.body.classList.add("editing");
  ct.querySelectorAll(".bw").forEach((w) => {
    setupEditable(w);
    addControls(w);
  });
  ct.addEventListener("contextmenu", handleCtx);
  rebuildTOC();
  updateWC();
  toast("Edit mode — click content to edit, press / to insert");
}
function exitEdit() {
  editing = false;
  document.body.classList.remove("editing");
  ct.querySelectorAll("[contenteditable]").forEach((el) =>
    el.removeAttribute("contenteditable"),
  );
  ct.querySelectorAll(".bctrl").forEach((b) => b.remove());
  ct.removeEventListener("contextmenu", handleCtx);
  hideCtx();
  hideFP();
  closeIbar();
  closeSlash();
  rebuildTOC();
  updateWC();
toast("wait saving...");
  setTimeout(()=>{
     savePage();
  },5000);
  
}

async function savePage() {
    const toc = document.querySelector("#tocl").outerHTML;
    const ct  = document.querySelector("#ct").outerHTML;

    const savable = {
        toc,
        ct,
        _csrf_token: await window.behaviour.createCsrfToken(),
        id: JSON.parse(document.querySelector("#wiki-meta").textContent).id,
    };
    const response = await fetch("/wiki/editor/save",{
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(savable)
    });
    const data = await response.json();
    if (data.status) {
        toast("Changes saved successfully");
    }
    else {
        toast("Changes failed to save try again.");
    }
}

// ── COMMANDS ─────────────────────────────────────────────────────
function ex(cmd, val = null) {
  document.execCommand(cmd, false, val);
  updateWC();
}
function fmtBlk(tag) {
  if (tag && tag !== "Format…") ex("formatBlock", tag);
}
function applyFont(f) {
  if (!f) return;
  wrapSel("span", `font-family:${f}`);
}
function applySize(s) {
  if (!s) return;
  wrapSel("span", `font-size:${s}`);
}
function wrapSel(tag, style) {
  const sel = window.getSelection();
  if (!sel.rangeCount || sel.isCollapsed) return;
  const range = sel.getRangeAt(0);
  const el = document.createElement(tag);
  el.style.cssText = style;
  try {
    range.surroundContents(el);
  } catch (e) {
    const f = range.extractContents();
    el.appendChild(f);
    range.insertNode(el);
  }
}

// ── TOC REBUILD ───────────────────────────────────────────────────
function rebuildTOC() {
  const tocl = document.getElementById("tocl");
  const heads = ct.querySelectorAll("h1,h2,h3");
  let html = "",
    num = { h1: 0, h2: 0, h3: 0 };
  heads.forEach((h) => {
    const tag = h.tagName.toLowerCase();
    if (!h.id)
      h.id =
        "h-" +
          h.textContent
            .trim()
            .toLowerCase()
            .replace(/\s+/g, "-")
            .replace(/[^a-z0-9-]/g, "")
            .slice(0, 40) || "h" + Date.now();
    if (tag === "h1") {
      num.h1++;
      num.h2 = 0;
      num.h3 = 0;
    } else if (tag === "h2") {
      num.h2++;
      num.h3 = 0;
    } else num.h3++;
    const n =
      tag === "h1"
        ? `${num.h1}.`
        : tag === "h2"
          ? `${num.h1}.${num.h2}.`
          : `${num.h1}.${num.h2}.${num.h3}.`;
    const cls = tag === "h2" ? "ti2" : tag === "h3" ? "ti3" : "";
    html += `<li><a href="#${h.id}" class="${cls}"><span class="tn">${n}</span>${h.textContent.trim()}</a></li>`;
  });
  tocl.innerHTML =
    html ||
    '<li style="font-family:var(--fu);font-size:12px;color:var(--ink3);padding:4px 8px">No headings yet</li>';
  // update any inline ctoc blocks
  ct.querySelectorAll(".ctoc").forEach((el) => {
    el.innerHTML =
      "<strong>Contents</strong>" +
      buildCTOCHTML().replace('<div class="ctoc">', "").replace("</div>", "");
  });
}

// ── TOC ADD ENTRY (sidebar buttons) ──────────────────────────────
// Adds a real heading block at the end of content AND updates TOC
function tocAddEntry(level) {
  const id = "h-" + Date.now();
  const text = level === "h2" ? "New Section" : "New Sub-section";
  const w = mkWrap(`<${level} id="${id}">${text}</${level}>`);
  ct.appendChild(w);
  rebuildTOC();
  const ed = w.querySelector('[contenteditable="true"]');
  if (ed) {
    ed.focus();
    // Select all text so user can type to replace
    const range = document.createRange();
    range.selectNodeContents(ed);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }
  updateWC();
  toast(text + " added to page");
}

// ── INSERT CTOC BLOCK ─────────────────────────────────────────────
function insertCTOCBlock() {
  const w = mkWrap(buildCTOCHTML());
  ct.appendChild(w);
  rebuildTOC();
  toast("Contents block inserted");
}

// ── CURSOR + BUTTON ───────────────────────────────────────────────
ct.addEventListener("keyup", updateCPlus);
ct.addEventListener("click", updateCPlus);

function updateCPlus() {
  if (!editing) {
    document.getElementById("cplus").style.display = "none";
    return;
  }
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  const range = sel.getRangeAt(0);
  if (!ct.contains(range.commonAncestorContainer)) return;
  // find nearest .bw ancestor
  let node = range.startContainer;
  let bw = null;
  while (node && node !== ct) {
    if (node.classList && node.classList.contains("bw")) {
      bw = node;
      break;
    }
    node = node.parentNode;
  }
  cplusTarget = bw;
  const rect = range.getBoundingClientRect();
  if (!rect || (rect.width === 0 && rect.height === 0)) return;
  const plus = document.getElementById("cplus");
  const artLeft = document
    .getElementById("article")
    .getBoundingClientRect().left;
  plus.style.display = "flex";
  plus.style.left = artLeft - 36 + "px";
  plus.style.top = rect.top + rect.height / 2 - 11 + "px";
}

// ── INLINE INSERT BAR ─────────────────────────────────────────────
let ibarOpen = false;

function openIbar() {
  closeSlash();
  const bar = document.getElementById("ibar");
  const plus = document.getElementById("cplus");
  const pr = plus.getBoundingClientRect();
  bar.classList.add("open");
  ibarOpen = true;
  // Set insertAfter from cursor target if not already set by block + button
  if (!insertAfter) insertAfter = cplusTarget;
  setTimeout(() => {
    const bw = bar.offsetWidth || 600;
    let left = pr.right + 8;
    if (left + bw > window.innerWidth - 8)
      left = Math.max(8, window.innerWidth - bw - 8);
    bar.style.left = left + "px";
    bar.style.top = pr.top - 4 + "px";
  }, 0);
}
function closeIbar() {
  document.getElementById("ibar").classList.remove("open");
  ibarOpen = false;
}
// ibDo: block-type inserts from ibar
function ibDo(id) {
  // insertAfter is already set (from cplus or block + btn)
  insertBlock(id);
}
// ibModal: modal-based inserts
function ibModal(id) {
  closeIbar();
  openModal(id);
}

// ── SLASH MENU ────────────────────────────────────────────────────
let slashOpen = false;
let slashPos = null; // saved sel before opening

function openSlash() {
  closeIbar();
  saveSel();
  slashPos = savedSel;
  slashSel = 0;
  renderSlash("");
  const menu = document.getElementById("smenu");
  menu.classList.add("open");
  slashOpen = true;
  // position at cursor
  const sel = window.getSelection();
  let x = 200,
    y = 200;
  if (sel && sel.rangeCount) {
    const r = sel.getRangeAt(0).getBoundingClientRect();
    x = r.left;
    y = r.bottom + 6;
    if (x + 320 > window.innerWidth) x = window.innerWidth - 324;
    if (y + 340 > window.innerHeight) y = r.top - 346;
    if (y < 8) y = 8;
  }
  menu.style.left = x + "px";
  menu.style.top = y + "px";
  document.getElementById("sinput").value = "";
  setTimeout(() => document.getElementById("sinput").focus(), 30);
}
function closeSlash() {
  document.getElementById("smenu").classList.remove("open");
  slashOpen = false;
  slashSel = 0;
}
function renderSlash(q) {
  const f = q.toLowerCase();
  slashFiltered = f
    ? slashAll.filter(
        (b) =>
          b.lbl.toLowerCase().includes(f) ||
          b.desc.toLowerCase().includes(f) ||
          b.grp.toLowerCase().includes(f),
      )
    : slashAll;
  slashSel = 0;
  const grps = {};
  slashFiltered.forEach((b) => {
    (grps[b.grp] = grps[b.grp] || []).push(b);
  });
  let html = "";
  Object.entries(grps).forEach(([g, items]) => {
    html += `<span class="sg">${g}</span>`;
    items.forEach((b) => {
      html += `<div class="si" data-id="${b.id}" onclick="slashPick('${b.id}')"><div class="si-ic">${b.ic}</div><div><div class="si-lbl">${b.lbl}</div><div class="si-desc">${b.desc}</div></div></div>`;
    });
  });
  document.getElementById("slist").innerHTML =
    html ||
    `<div style="padding:16px;text-align:center;font-family:var(--fu);font-size:13px;color:var(--ink3)">No results</div>`;
  hlSlash();
}
function filterSlash(q) {
  renderSlash(q);
}
function hlSlash() {
  const items = document.querySelectorAll("#slist .si");
  items.forEach((el, i) => el.classList.toggle("sel", i === slashSel));
  if (items[slashSel]) items[slashSel].scrollIntoView({ block: "nearest" });
}
function slashKey(e) {
  const items = document.querySelectorAll("#slist .si");
  if (e.key === "ArrowDown") {
    e.preventDefault();
    slashSel = Math.min(slashSel + 1, items.length - 1);
    hlSlash();
  } else if (e.key === "ArrowUp") {
    e.preventDefault();
    slashSel = Math.max(slashSel - 1, 0);
    hlSlash();
  } else if (e.key === "Enter") {
    e.preventDefault();
    if (items[slashSel]) slashPick(items[slashSel].dataset.id);
  } else if (e.key === "Escape") {
    e.preventDefault();
    closeSlash();
  }
}
function slashPick(id) {
  closeSlash();
  // restore selection so insertAfter is based on where cursor was
  if (slashPos) {
    savedSel = slashPos;
    restSel();
  }
  // find bw from selection
  const sel = window.getSelection();
  if (sel && sel.rangeCount) {
    let n = sel.getRangeAt(0).startContainer;
    let bw = null;
    while (n && n !== ct) {
      if (n.classList && n.classList.contains("bw")) {
        bw = n;
        break;
      }
      n = n.parentNode;
    }
    insertAfter = bw;
  }
  insertBlock(id);
}
// Trigger slash on "/" key at empty/start-of-line position
ct.addEventListener("keydown", (e) => {
  if (!editing) return;
  if (e.key === "/" && !e.ctrlKey && !e.metaKey) {
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      const range = sel.getRangeAt(0);
      const node = range.startContainer;
      const offset = range.startOffset;
      const txt = node.textContent || "";
      const before = txt.slice(0, offset);
      if (before === "" || /\s$/.test(before)) {
        setTimeout(() => openSlash(), 0);
      }
    }
  }
});

// ── INLINE FORMAT POPUP ───────────────────────────────────────────
function hideFP() {
  document.getElementById("fp").classList.remove("open");
}
document.addEventListener("mouseup", (e) => {
  if (!editing) return;
  if (
    e.target.closest("#fp") ||
    e.target.closest("#smenu") ||
    e.target.closest("#mo") ||
    e.target.closest("#ctx")
  )
    return;
  setTimeout(() => {
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.toString().trim()) {
      hideFP();
      return;
    }
    if (!ct.contains(sel.anchorNode)) {
      hideFP();
      return;
    }
    const range = sel.getRangeAt(0),
      rect = range.getBoundingClientRect();
    const popup = document.getElementById("fp");
    popup.classList.add("open");
    const pw = popup.offsetWidth || 280;
    let left = rect.left + rect.width / 2 - pw / 2,
      top = rect.top - 48;
    if (left < 8) left = 8;
    if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8;
    if (top < 8) top = rect.bottom + 8;
    popup.style.left = left + "px";
    popup.style.top = top + "px";
  }, 10);
});
document.addEventListener("mousedown", (e) => {
  if (!e.target.closest("#fp")) hideFP();
});

// ── CONTEXT MENU ─────────────────────────────────────────────────
function handleCtx(e) {
  if (!editing) return;
  const wrap = e.target.closest(".bw");
  if (!wrap) return;
  e.preventDefault();
  ctxTarget = wrap;
  const ctx = document.getElementById("ctx");
  ctx.classList.add("open");
  let x = e.clientX,
    y = e.clientY;
  if (x + 200 > window.innerWidth) x = window.innerWidth - 204;
  if (y + 220 > window.innerHeight) y = window.innerHeight - 224;
  ctx.style.left = x + "px";
  ctx.style.top = y + "px";
}
function hideCtx() {
  document.getElementById("ctx").classList.remove("open");
  ctxTarget = null;
}
function ctxAct(a) {
  const t = ctxTarget;
  hideCtx();
  if (!t) return;
  if (a === "up" && t.previousElementSibling)
    t.previousElementSibling.before(t);
  else if (a === "down" && t.nextElementSibling) t.nextElementSibling.after(t);
  else if (a === "dup") {
    const c = t.cloneNode(true);
    setupEditable(c);
    addControls(c);
    t.after(c);
  } else if (a === "del") {
    if (confirm("Delete block?")) {
      t.remove();
    }
  } else if (a === "addabove") {
    insertAfter = t.previousElementSibling;
    openIbar();
  } else if (a === "addbelow") {
    insertAfter = t;
    openIbar();
  }
  rebuildTOC();
  updateWC();
}

// ── REFS ──────────────────────────────────────────────────────────
function togRef() {
  const p = document.getElementById("refp"),
    b = document.getElementById("refbtn");
  p.classList.toggle("open");
  b.classList.toggle("act");
  if (p.classList.contains("open")) renderRefs();
}
function renderRefs() {
  document.getElementById("rpb").innerHTML = refs
    .map(
      (r) =>
        `<div class="re"><div class="re-hd"><div class="rn">${r.id}</div><span style="font-family:var(--fu);font-size:12px;color:var(--ink2);flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">${r.text.slice(0, 45)}…</span><button class="redel" onclick="delRef(${r.id})">×</button></div><div class="re-body"><textarea rows="2" onblur="updRef(${r.id},this.value)">${r.text}</textarea></div></div>`,
    )
    .join("");
}
async function addRef() {
  refs.push({ id: nextRef++, text: "New reference. Click to edit." });
  renderRefs();
  const response = await fetch('/wiki/editor/refs',{
    method: 'POST',
    headers:{
        "Content-Type": "application/json",

    },
    body: JSON.stringify( {
        _csrf_token: await window.behaviour.createCsrfToken(),
        refs: refs,
        id: wiki.id
    })
  })
  updateRefsBlock();
}
async function delRef(id) {
  refs = refs.filter((r) => r.id !== id);
  const response = await fetch('/wiki/editor/refs',{
    method: 'POST',
    headers:{
        "Content-Type": "application/json",

    },
    body: JSON.stringify({
        _csrf_token: await window.behaviour.createCsrfToken(),
        refs: refs,
        id: wiki.id
    })
  })
  renderRefs();
  updateRefsBlock();
}
async function updRef(id, val) {
  const r = refs.find((r) => r.id === id);
  if (r) r.text = val;
  const response = await fetch('/wiki/editor/refs',{
    method: 'POST',
    headers:{
        "Content-Type": "application/json",

    },
    body: JSON.stringify( {
        _csrf_token: await window.behaviour.createCsrfToken(),
        refs: refs,
        id: wiki.id
    })
  })
  updateRefsBlock();
}
function updateRefsBlock() {
  const rb = document.getElementById("refs-block");
  if (rb) rb.outerHTML = buildRefsHTML();
}
function insertRefCursor() {
  const id = refs.length ? refs[refs.length - 1].id : 1;
  restSel();
  ex(
    "insertHTML",
    `<sup class="fn" onclick="jumpRef(${id})" title="Ref ${id}">[${id}]</sup>`,
  );
  toast(`[${id}] inserted`);
}
function addFootnote() {
  const id = nextRef++;
  refs.push({ id, text: "New footnote." });
  ex(
    "insertHTML",
    `<sup class="fn" onclick="jumpRef(${id})" title="Ref ${id}">[${id}]</sup>`,
  );
  if (document.getElementById("refp").classList.contains("open")) renderRefs();
  updateRefsBlock();
  toast(`Footnote [${id}] added`);
}
function jumpRef(n) {
  const el = document.getElementById("ref-" + n);
  if (el) {
    el.scrollIntoView({ behavior: "smooth", block: "center" });
    el.style.background = "#fef9c3";
    setTimeout(() => (el.style.background = ""), 1600);
  }
}

// ── SOURCE ────────────────────────────────────────────────────────
function togSrc() {
  const p = document.getElementById("sp"),
    b = document.getElementById("srcbtn");
  if (p.classList.contains("open")) {
    closeSrc();
  } else {
    document.getElementById("spita").value = ct.innerHTML;
    p.classList.add("open");
    b.classList.add("act");
    b.innerHTML = "× Source";
  }
}
function closeSrc() {
  document.getElementById("sp").classList.remove("open");
  const b = document.getElementById("srcbtn");
  b.classList.remove("act");
  b.innerHTML =
    '<svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="5,4 1,8 5,12"/><polyline points="11,4 15,8 11,12"/></svg> Source';
}
function applySrc() {
  ct.innerHTML = document.getElementById("spita").value;
  if (editing)
    ct.querySelectorAll(".bw").forEach((w) => {
      setupEditable(w);
      addControls(w);
    });
  closeSrc();
  rebuildTOC();
  updateWC();
  toast("Source applied");
}

// ── MODALS ────────────────────────────────────────────────────────
const MDEFS = {
  "img-url": {
    title: "Insert Image",
    body: `<label>Image URL</label><input id="m1" type="url" placeholder="https://…"><label>Alt text</label><input id="m2" placeholder="Description"><div class="r2"><div><label>Width</label><input id="m3" placeholder="100% or 400px"></div><div><label>Float</label><select id="m4"><option value="">None</option><option value="margin:0 auto;display:block">Center</option><option value="float:left;margin:0 16px 8px 0">Left</option><option value="float:right;margin:0 0 8px 16px">Right</option></select></div></div>`,
    ok() {
      const src = document.getElementById("m1").value;
      if (!src) return;
      const alt = document.getElementById("m2").value,
        w = document.getElementById("m3").value,
        al = document.getElementById("m4").value;
      let s = [w ? `width:${w}` : "", al].filter(Boolean).join(";");
      placeBlock(
        mkWrap(
          `<img src="${src}" alt="${alt}"${s ? ` style="${s}"` : ""} loading="lazy">`,
        ),
      );
      toast("Image inserted");
    },
  },
  "img-up": {
    title: "Upload Image",
    body: `<div class="fdrop" id="fd">Click or drag image here</div><input id="ff" type="file" accept="image/*" style="display:none"><p id="fp2" style="font-size:12px;color:var(--ink3);margin-bottom:10px"></p><label>Width (optional)</label><input id="fw" placeholder="100% or 400px">`,
    init() {
      setupDrop("fd", "ff", true);
    },
    ok() {
      if (!window._up) {
        toast("No image");
        return;
      }
      const w = document.getElementById("fw").value;
      placeBlock(
        mkWrap(
          `<img src="${window._up.src}" alt="${window._up.name}"${w ? ` style="width:${w}"` : ""} loading="lazy">`,
        ),
      );
      window._up = null;
      toast("Image embedded");
    },
  },
  figure: {
    title: "Figure + Caption",
    body: `<label>Image URL</label><input id="m1" type="url" placeholder="https://…"><label>Caption text</label><input id="m2" placeholder="Figure caption"><label>Width (optional)</label><input id="m3" placeholder="100% or 400px">`,
    ok() {
      const src = document.getElementById("m1").value;
      if (!src) return;
      const cap = document.getElementById("m2").value || "Figure",
        w = document.getElementById("m3").value;
      placeBlock(
        mkWrap(
          `<figure${w ? ` style="width:${w}"` : ""}><img src="${src}" alt="${cap}" loading="lazy"><figcaption>${cap}</figcaption></figure>`,
        ),
      );
      toast("Figure inserted");
    },
  },
  video: {
    title: "Embed Video",
    body: `<label>YouTube or Vimeo URL</label><input id="m1" type="url" placeholder="https://www.youtube.com/watch?v=…"><p style="font-size:12px;color:var(--ink3);margin-top:-8px;margin-bottom:12px">Paste any YouTube or Vimeo link — auto-converts</p>`,
    ok() {
      const url = document.getElementById("m1").value.trim();
      if (!url) return;
      let src = "";
      const yt = url.match(
          /(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
        ),
        vm = url.match(/vimeo\.com\/(\d+)/);
      if (yt) src = `https://www.youtube.com/embed/${yt[1]}`;
      else if (vm) src = `https://player.vimeo.com/video/${vm[1]}`;
      else src = url;
      placeBlock(
        mkWrap(
          `<div class="vwrap"><iframe src="${src}" frameborder="0" allowfullscreen loading="lazy"></iframe></div>`,
        ),
      );
      toast("Video embedded");
    },
  },
  audio: {
    title: "Embed Audio",
    body: `<label>Audio URL or embed link</label><input id="m1" type="url" placeholder="https://…"><label>Type</label><select id="m2"><option value="file">Direct audio file (.mp3/.ogg/.wav)</option><option value="sc">SoundCloud</option><option value="sp">Spotify</option></select><label>Title (optional)</label><input id="m3" placeholder="Track title">`,
    ok() {
      const url = document.getElementById("m1").value.trim();
      if (!url) return;
      const type = document.getElementById("m2").value,
        title = document.getElementById("m3").value;
      let html = "";
      if (type === "file")
        html = `<figure style="margin:1em 0">${title ? `<figcaption style="font-family:var(--fu);font-size:.85em;color:var(--ink3);margin-bottom:5px">🎵 ${title}</figcaption>` : ""}<audio controls style="width:100%;border-radius:6px"><source src="${url}">Browser does not support audio.</audio></figure>`;
      else if (type === "sc")
        html = `<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="https://w.soundcloud.com/player/?url=${encodeURIComponent(url)}&color=%232d6a4f&auto_play=false" style="border-radius:6px"></iframe>`;
      else
        html = `<iframe style="border-radius:12px;width:100%;height:152px" src="${url}" frameborder="0" allowfullscreen allow="autoplay" loading="lazy"></iframe>`;
      placeBlock(mkWrap(html));
      toast("Audio embedded");
    },
  },
  link: {
    title: "Insert Link",
    body: `<label>Link text</label><input id="m1" placeholder="Display text"><label>URL</label><input id="m2" type="url" placeholder="https://"><label>Open in</label><select id="m3"><option value="">Same tab</option><option value="_blank">New tab</option></select>`,
    ok() {
      const text = document.getElementById("m1").value,
        url = document.getElementById("m2").value;
      if (!url) return;
      const target = document.getElementById("m3").value;
      restSel();
      ex(
        "insertHTML",
        `<a href="${url}"${target ? ` target="${target}"` : ""} rel="noopener">${text || url}</a>`,
      );
      toast("Link inserted");
    },
  },
  table: {
    title: "Insert Table",
    body: `<div id="tg-wrap"><div style="font-family:var(--fu);font-size:12px;color:var(--ink3);margin-bottom:8px">Hover to select size, click to insert</div><div class="tg" id="tg" style="grid-template-columns:repeat(8,26px)"></div><div id="tg-lbl" style="font-family:var(--fu);font-size:12px;color:var(--ink3);margin-top:6px">Move mouse over grid</div><label style="margin-top:10px">Header row <input type="checkbox" id="thdr" checked style="width:auto;margin-left:4px;margin-bottom:0"></label></div>`,
    init() {
      const tg = document.getElementById("tg");
      let h = "";
      for (let r = 1; r <= 6; r++)
        for (let c = 1; c <= 8; c++)
          h += `<div class="tc" data-r="${r}" data-c="${c}" onmouseover="hlTbl(${r},${c})" onclick="pickTbl(${r},${c})"></div>`;
      tg.innerHTML = h;
    },
    ok() {},
  },
  dtable: {
    title: "Definition Table",
    body: `<label>Rows</label><input id="m1" type="number" value="4" min="1" max="20"><label>Left header</label><input id="m2" placeholder="Property"><label>Right header</label><input id="m3" placeholder="Value">`,
    ok() {
      const rows = parseInt(document.getElementById("m1").value) || 4,
        h1 = document.getElementById("m2").value || "Property",
        h2 = document.getElementById("m3").value || "Value";
      let html = `<table><thead><tr><th style="width:40%">${h1}</th><th>${h2}</th></tr></thead><tbody>`;
      for (let r = 0; r < rows; r++)
        html += `<tr><td>Key ${r + 1}</td><td>Value ${r + 1}</td></tr>`;
      html += "</tbody></table>";
      placeBlock(mkWrap(html));
      toast("Table inserted");
    },
  },
  mg2: {
    title: "2-Column Media Grid",
    body: `<label>Image 1 URL</label><input id="m1" type="url" placeholder="https://…"><label>Caption 1</label><input id="mc1" placeholder="Optional"><label>Image 2 URL</label><input id="m2" type="url" placeholder="https://…"><label>Caption 2</label><input id="mc2" placeholder="Optional">`,
    ok() {
      const imgs = [1, 2].map((i) => ({
        src: document.getElementById("m" + i).value,
        cap: document.getElementById("mc" + i).value,
      }));
      const figs = imgs
        .map(({ src, cap }) =>
          src
            ? `<figure><img src="${src}" alt="${cap}" style="width:100%;height:180px;object-fit:cover" loading="lazy">${cap ? `<figcaption>${cap}</figcaption>` : ""}</figure>`
            : '<figure style="background:var(--rule2);height:180px;border-radius:6px"></figure>',
        )
        .join("");
      placeBlock(mkWrap(`<div class="mgrid mg2">${figs}</div>`));
      toast("Media grid 2 inserted");
    },
  },
  mg3: {
    title: "3-Column Media Grid",
    body: `<label>Image 1 URL</label><input id="m1" type="url"><label>Cap 1</label><input id="mc1"><label>Image 2 URL</label><input id="m2" type="url"><label>Cap 2</label><input id="mc2"><label>Image 3 URL</label><input id="m3" type="url"><label>Cap 3</label><input id="mc3">`,
    ok() {
      const imgs = [1, 2, 3].map((i) => ({
        src: document.getElementById("m" + i).value,
        cap: document.getElementById("mc" + i).value,
      }));
      const figs = imgs
        .map(({ src, cap }) =>
          src
            ? `<figure><img src="${src}" alt="${cap}" style="width:100%;height:140px;object-fit:cover" loading="lazy">${cap ? `<figcaption>${cap}</figcaption>` : ""}</figure>`
            : '<figure style="background:var(--rule2);height:140px;border-radius:6px"></figure>',
        )
        .join("");
      placeBlock(mkWrap(`<div class="mgrid mg3">${figs}</div>`));
      toast("Media grid 3 inserted");
    },
  },
  "file-up": {
    title: "Upload File",
    body: `<div class="fdrop" id="fd">Click or drag any file here</div><input id="ff" type="file" style="display:none"><p id="fp2" style="font-size:12px;color:var(--ink3);margin-bottom:10px"></p><label>Link label</label><input id="flbl" placeholder="Filename">`,
    init() {
      setupDrop("fd", "ff", false);
    },
    ok() {
      if (!window._up) {
        toast("No file");
        return;
      }
      const lbl = document.getElementById("flbl").value || window._up.name;
      placeBlock(
        mkWrap(
          `<p>📎 <a href="${window._up.src}" download="${window._up.name}">${lbl}</a></p>`,
        ),
      );
      window._up = null;
      toast("File attached");
    },
  },
  form: {
    title: "Insert Form",
    body: `<label>Form title</label><input id="m1" placeholder="e.g. Contact Us"><label>Fields</label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px;font-family:var(--fu);font-size:13px">
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-n" checked style="width:auto;margin:0"> Name</label>
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-e" checked style="width:auto;margin:0"> Email</label>
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-p" style="width:auto;margin:0"> Phone</label>
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-s" style="width:auto;margin:0"> Subject</label>
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-m" checked style="width:auto;margin:0"> Message</label>
      <label style="display:flex;align-items:center;gap:6px;color:var(--ink)"><input type="checkbox" id="ff-b" checked style="width:auto;margin:0"> Submit btn</label>
    </div>
    <label>Button label</label><input id="m2" value="Submit">
    <label>Style</label><select id="m3"><option value="card">Card (bordered)</option><option value="min">Minimal</option></select>`,
    ok() {
      const title = document.getElementById("m1").value,
        btn = document.getElementById("m2").value || "Submit",
        style = document.getElementById("m3").value;
      const wrap =
        style === "card"
          ? "border:1px solid var(--rule);border-radius:8px;padding:24px;"
          : "";
      const fs =
        "width:100%;border:1px solid var(--rule);border-radius:var(--r);padding:9px 12px;font-family:var(--fu);font-size:14px;margin-bottom:14px;outline:none;display:block";
      const ls =
        "font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);display:block;margin-bottom:4px";
      let f = "";
      if (document.getElementById("ff-n").checked)
        f += `<label style="${ls}">Full Name</label><input type="text" placeholder="Your name" style="${fs}">`;
      if (document.getElementById("ff-e").checked)
        f += `<label style="${ls}">Email</label><input type="email" placeholder="you@example.com" style="${fs}">`;
      if (document.getElementById("ff-p").checked)
        f += `<label style="${ls}">Phone</label><input type="tel" placeholder="+1 555 000 0000" style="${fs}">`;
      if (document.getElementById("ff-s").checked)
        f += `<label style="${ls}">Subject</label><input type="text" placeholder="Subject" style="${fs}">`;
      if (document.getElementById("ff-m").checked)
        f += `<label style="${ls}">Message</label><textarea rows="4" placeholder="Your message…" style="${fs};resize:vertical;min-height:90px"></textarea>`;
      const sb = document.getElementById("ff-b").checked
        ? `<button type="submit" style="background:var(--acc);color:#fff;border:none;border-radius:20px;padding:10px 28px;font-family:var(--fu);font-size:14px;font-weight:500;cursor:pointer">${btn}</button>`
        : "";
      placeBlock(
        mkWrap(
          `<div style="${wrap}margin:1em 0">${title ? `<h3 style="font-family:var(--fh);margin-bottom:18px">${title}</h3>` : ""}<form onsubmit="event.preventDefault();alert('Form submitted!')">${f}${sb}</form></div>`,
        ),
      );
      toast("Form inserted");
    },
  },
  "f-text": {
    title: "Text Input",
    body: `<label>Label</label><input id="m1" placeholder="Field label"><label>Placeholder</label><input id="m2" placeholder="Hint"><label>Type</label><select id="m3"><option>text</option><option>email</option><option>tel</option><option>number</option><option>date</option><option>url</option><option>password</option></select>`,
    ok() {
      const l = document.getElementById("m1").value,
        p = document.getElementById("m2").value,
        t = document.getElementById("m3").value;
      const s =
        "width:100%;border:1px solid var(--rule);border-radius:var(--r);padding:9px 12px;font-family:var(--fu);font-size:14px;outline:none;display:block";
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0">${l ? `<label style="font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);display:block;margin-bottom:4px">${l}</label>` : ""}<input type="${t}" placeholder="${p}" style="${s}"></div>`,
        ),
      );
      toast("Input added");
    },
  },
  "f-area": {
    title: "Text Area",
    body: `<label>Label</label><input id="m1" placeholder="Label"><label>Placeholder</label><input id="m2" placeholder="Hint"><label>Rows</label><input id="m3" type="number" value="4" min="2" max="20">`,
    ok() {
      const l = document.getElementById("m1").value,
        p = document.getElementById("m2").value,
        r = document.getElementById("m3").value || 4;
      const s =
        "width:100%;border:1px solid var(--rule);border-radius:var(--r);padding:9px 12px;font-family:var(--fu);font-size:14px;resize:vertical;outline:none;display:block";
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0">${l ? `<label style="font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);display:block;margin-bottom:4px">${l}</label>` : ""}<textarea rows="${r}" placeholder="${p}" style="${s}"></textarea></div>`,
        ),
      );
      toast("Textarea added");
    },
  },
  "f-sel": {
    title: "Dropdown",
    body: `<label>Label</label><input id="m1" placeholder="Label"><label>Options (one per line)</label><textarea id="m2" placeholder="Option A&#10;Option B&#10;Option C" rows="5"></textarea>`,
    ok() {
      const l = document.getElementById("m1").value;
      const opts = (document.getElementById("m2").value || "")
        .split("\n")
        .filter(Boolean)
        .map((o) => `<option>${o.trim()}</option>`)
        .join("");
      const s =
        "width:100%;border:1px solid var(--rule);border-radius:var(--r);padding:9px 12px;font-family:var(--fu);font-size:14px;outline:none;display:block;background:var(--sf)";
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0">${l ? `<label style="font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);display:block;margin-bottom:4px">${l}</label>` : ""}<select style="${s}"><option>— Select —</option>${opts}</select></div>`,
        ),
      );
      toast("Dropdown added");
    },
  },
  "f-chk": {
    title: "Checkbox Group",
    body: `<label>Group label</label><input id="m1" placeholder="Group title"><label>Options (one per line)</label><textarea id="m2" placeholder="Option A&#10;Option B&#10;Option C" rows="5"></textarea>`,
    ok() {
      const l = document.getElementById("m1").value;
      const opts = (document.getElementById("m2").value || "A\nB\nC")
        .split("\n")
        .filter(Boolean);
      const items = opts
        .map(
          (o) =>
            `<label style="display:flex;align-items:center;gap:8px;font-family:var(--fu);font-size:14px;margin:.35em 0;cursor:pointer"><input type="checkbox" style="width:16px;height:16px;accent-color:var(--acc)"> ${o.trim()}</label>`,
        )
        .join("");
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0">${l ? `<div style="font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);margin-bottom:6px">${l}</div>` : ""}<div>${items}</div></div>`,
        ),
      );
      toast("Checkboxes added");
    },
  },
  "f-rad": {
    title: "Radio Group",
    body: `<label>Group label</label><input id="m1" placeholder="Choose one"><label>Options (one per line)</label><textarea id="m2" placeholder="Option A&#10;Option B&#10;Option C" rows="5"></textarea>`,
    ok() {
      const l = document.getElementById("m1").value || "Choose one";
      const opts = (document.getElementById("m2").value || "A\nB\nC")
        .split("\n")
        .filter(Boolean);
      const gn = "rg" + Date.now();
      const items = opts
        .map(
          (o) =>
            `<label style="display:flex;align-items:center;gap:8px;font-family:var(--fu);font-size:14px;margin:.35em 0;cursor:pointer"><input type="radio" name="${gn}" style="width:16px;height:16px;accent-color:var(--acc)"> ${o.trim()}</label>`,
        )
        .join("");
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0"><div style="font-family:var(--fu);font-size:12px;font-weight:500;color:var(--ink3);margin-bottom:6px">${l}</div><div>${items}</div></div>`,
        ),
      );
      toast("Radio group added");
    },
  },
  "f-sub": {
    title: "Submit Button",
    body: `<label>Button text</label><input id="m1" value="Submit"><label>Style</label><select id="m2"><option value="pri">Primary (filled)</option><option value="out">Outline</option><option value="ghost">Ghost</option></select><label>Width</label><select id="m3"><option value="">Auto</option><option value="100%">Full width</option></select>`,
    ok() {
      const lbl = document.getElementById("m1").value || "Submit",
        style = document.getElementById("m2").value,
        w = document.getElementById("m3").value;
      const SS = {
        pri: `background:var(--acc);color:#fff;border:none;border-radius:20px;padding:10px 28px;font-family:var(--fu);font-size:14px;font-weight:500;cursor:pointer`,
        out: `background:transparent;color:var(--acc);border:2px solid var(--acc);border-radius:20px;padding:9px 28px;font-family:var(--fu);font-size:14px;font-weight:500;cursor:pointer`,
        ghost: `background:transparent;color:var(--acc);border:none;padding:9px 4px;font-family:var(--fu);font-size:14px;font-weight:500;cursor:pointer;text-decoration:underline`,
      };
      placeBlock(
        mkWrap(
          `<div style="margin:.8em 0"><button type="submit" style="${SS[style] || SS.pri}${w ? ";width:" + w : ""}">${lbl}</button></div>`,
        ),
      );
      toast("Button added");
    },
  },
  html: {
    title: "HTML Snippet",
    body: `<label>Paste HTML</label><textarea id="m1" placeholder="<div>…</div>" style="font-family:var(--fm);font-size:12px;min-height:120px"></textarea>`,
    ok() {
      const code = document.getElementById("m1").value;
      if (!code) return;
      restSel();
      ex("insertHTML", code);
      toast("HTML inserted");
    },
  },
};

function openModal(type) {
  saveSel();
  curModal = type;
  const cfg = MDEFS[type];
  if (!cfg) return;
  document.getElementById("mtitle").textContent = cfg.title;
  document.getElementById("mbody").innerHTML = cfg.body || "";
  document.getElementById("mok").style.display = type === "table" ? "none" : "";
  document.getElementById("mo").classList.add("open");
  if (cfg.init) cfg.init();
}
function closeModal() {
  document.getElementById("mo").classList.remove("open");
  curModal = null;
}
function modalOK() {
  if (curModal && MDEFS[curModal]) MDEFS[curModal].ok();
  closeModal();
}

// table grid helpers
function hlTbl(r, c) {
  document
    .querySelectorAll(".tc")
    .forEach((el) =>
      el.classList.toggle("on", +el.dataset.r <= r && +el.dataset.c <= c),
    );
  document.getElementById("tg-lbl").textContent = `${r} × ${c} table`;
}
function pickTbl(r, c) {
  const hdr = document.getElementById("thdr").checked;
  closeModal();
  let html = "<table>";
  if (hdr) {
    html += "<thead><tr>";
    for (let i = 0; i < c; i++) html += `<th>Col ${i + 1}</th>`;
    html += "</tr></thead>";
  }
  html += "<tbody>";
  for (let i = 0; i < (hdr ? r - 1 : r); i++) {
    html += "<tr>";
    for (let j = 0; j < c; j++) html += "<td>Cell</td>";
    html += "</tr>";
  }
  html += "</tbody></table>";
  placeBlock(mkWrap(html));
  toast(`Table ${r}×${c} inserted`);
}

// file drop helper
function setupDrop(dropId, inputId, imgOnly) {
  const drop = document.getElementById(dropId),
    inp = document.getElementById(inputId);
  if (!drop || !inp) return;
  drop.onclick = () => inp.click();
  drop.ondragover = (e) => {
    e.preventDefault();
    drop.classList.add("over");
  };
  drop.ondragleave = () => drop.classList.remove("over");
  drop.ondrop = (e) => {
    e.preventDefault();
    drop.classList.remove("over");
    load(e.dataTransfer.files[0]);
  };
  inp.onchange = () => load(inp.files[0]);
  function load(f) {
    if (!f) return;
    if (imgOnly && !f.type.startsWith("image/")) return;
    const r = new FileReader();
    r.onload = (e) => {
      window._up = { src: e.target.result, name: f.name };
      const p = document.getElementById("fp2");
      if (p) p.textContent = `✓ ${f.name} (${(f.size / 1024).toFixed(0)} KB)`;
      drop.textContent = `✓ ${f.name}`;
    };
    r.readAsDataURL(f);
  }
}

// ── SEL SAVE/RESTORE ──────────────────────────────────────────────
function saveSel() {
  const s = window.getSelection();
  if (s.rangeCount) savedSel = s.getRangeAt(0).cloneRange();
}
function restSel() {
  const s = window.getSelection();
  s.removeAllRanges();
  if (savedSel) s.addRange(savedSel);
}
function placeEnd(el) {
  const r = document.createRange(),
    s = window.getSelection();
  r.selectNodeContents(el);
  r.collapse(false);
  s.removeAllRanges();
  s.addRange(r);
}

// ── EXPORT ────────────────────────────────────────────────────────
function copyHTML() {
  navigator.clipboard.writeText(ct.innerHTML).then(() => toast("HTML copied"));
}
function dlHTML() {
  const clean = ct.cloneNode(true);
  clean.querySelectorAll(".bctrl").forEach((e) => e.remove());
  clean
    .querySelectorAll("[contenteditable]")
    .forEach((e) => e.removeAttribute("contenteditable"));
  const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Wiki Page</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=DM+Sans:wght@400;500&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Source Serif 4',serif;font-size:17px;line-height:1.85;color:#1c1a16;max-width:800px;margin:48px auto;padding:0 24px;background:#faf8f4}h1{font-family:'Playfair Display',serif;font-size:2.3em;font-weight:700;margin:.4em 0}h2{font-family:'Playfair Display',serif;font-size:1.45em;border-bottom:1px solid #e4e0d8;padding-bottom:6px;margin:2em 0 .55em}h3{font-family:'Playfair Display',serif;font-size:1.12em;margin:1.4em 0 .35em}h4{font-family:'DM Sans',sans-serif;font-size:.95em;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#8a8480;margin:1.2em 0 .3em}p{color:#4a4640;margin:.65em 0}blockquote{border-left:3px solid #c9a84c;margin:1.4em 0;padding:.75em 1.3em;background:#fdf8ee;font-style:italic;color:#4a4640}table{border-collapse:collapse;width:100%;font-family:'DM Sans',sans-serif;font-size:.89em;margin:1.1em 0}th,td{border:1px solid #e4e0d8;padding:8px 13px}th{background:#f0ece4}pre{background:#1c1a16;color:#e8e4d8;padding:1.1em;border-radius:6px;overflow-x:auto;font-family:'JetBrains Mono',monospace;font-size:.87em}code{font-family:'JetBrains Mono',monospace;font-size:.86em;background:#f0ece4;padding:2px 6px;border-radius:3px;color:#b5451b}pre code{background:transparent;color:inherit}a{color:#2d6a4f}img{max-width:100%;border-radius:6px}figure{margin:1.2em 0;text-align:center}figcaption{font-family:'DM Sans',sans-serif;font-size:.82em;color:#8a8480;margin-top:6px}hr{border:none;border-top:1px solid #e4e0d8;margin:1.8em 0}.co{border-radius:0 6px 6px 0;padding:13px 17px;margin:1.1em 0;font-family:'DM Sans',sans-serif;font-size:.94em}.co strong{display:block;margin-bottom:4px}.cols{display:grid;gap:20px;margin:1.1em 0}.c2{grid-template-columns:1fr 1fr}.c3{grid-template-columns:1fr 1fr 1fr}.c13{grid-template-columns:1fr 3fr}.c31{grid-template-columns:3fr 1fr}.cc{border:1px solid #e4e0d8;border-radius:6px;padding:16px}.mgrid{display:grid;gap:8px;margin:1.1em 0}.mg2{grid-template-columns:1fr 1fr}.mg3{grid-template-columns:1fr 1fr 1fr}.vwrap{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px}.vwrap iframe{position:absolute;top:0;left:0;width:100%;height:100%}.sec{border:1px solid #e4e0d8;border-radius:8px;margin:1.2em 0;overflow:hidden}.sec-hd{display:flex;align-items:center;gap:8px;padding:12px 18px;background:#f0ece4;cursor:pointer;font-family:'Playfair Display',serif;font-size:1.05em;font-weight:600}.sec-arrow{font-size:11px;color:#8a8480;transition:transform .2s}.sec.open .sec-arrow{transform:rotate(90deg)}.sec-body{padding:18px 22px;display:none}.sec.open .sec-body{display:block}.refs{font-family:'DM Sans',sans-serif;font-size:.87em;color:#4a4640}.refs ol{margin-left:1.4em}.refs li{margin:.35em 0;line-height:1.6}.ctoc{background:#f0ece4;border:1px solid #e4e0d8;border-radius:8px;padding:14px 18px;font-family:'DM Sans',sans-serif;font-size:.9em;display:inline-block;min-width:200px}.ctoc strong{font-size:.8em;letter-spacing:.5px;text-transform:uppercase;color:#8a8480;display:block;margin-bottom:8px}.ctoc ol{margin-left:1.2em}.ctoc li{margin:.18em 0;line-height:1.4;font-size:12px}.ctoc a{color:#2d6a4f;text-decoration:none}sup.fn{font-size:.72em;color:#1a56db;cursor:pointer;text-decoration:underline;text-decoration-style:dotted}.bw{position:relative}</style>
<script>document.querySelectorAll('.sec-hd').forEach(h=>h.onclick=()=>h.parentElement.classList.toggle('open'));document.querySelectorAll('sup.fn').forEach(s=>{const n=s.textContent.replace(/\[|\]/g,'');s.onclick=()=>{const el=document.getElementById('ref-'+n);if(el){el.scrollIntoView({behavior:'smooth',block:'center'});el.style.background='#fef9c3';setTimeout(()=>el.style.background='',1500);}};});<\/script>
</head><body>${clean.innerHTML}</body></html>`;
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([html], { type: "text/html" }));
  a.download = "wiki-page.html";
  a.click();
  toast("Downloaded");
}

// ── WORD COUNT ────────────────────────────────────────────────────
function updateWC() {
  const t = ct.innerText || "";
  document.getElementById("wcw").textContent = t.trim()
    ? t.trim().split(/\s+/).length
    : 0;
  document.getElementById("wcc").textContent = t.replace(/\n/g, "").length;
}

// ── TOAST ─────────────────────────────────────────────────────────
function toast(msg) {
  const t = document.getElementById("tst");
  t.textContent = msg;
  t.classList.add("show");
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove("show"), 2200);
}

// ── GLOBAL EVENTS ─────────────────────────────────────────────────
document.addEventListener("click", (e) => {
  if (!e.target.closest("#ctx")) hideCtx();
  if (!e.target.closest("#smenu") && !e.target.closest("#sinput")) closeSlash();
  if (
    !e.target.closest("#ibar") &&
    !e.target.closest("#cplus") &&
    !e.target.closest(".abtn")
  )
    closeIbar();
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeModal();
    closeSrc();
    hideFP();
    hideCtx();
    closeIbar();
    closeSlash();
  }
  if (!editing) return;
  if (e.ctrlKey || e.metaKey) {
    const m = { b: "bold", i: "italic", u: "underline", z: "undo", y: "redo" };
    if (m[e.key]) {
      e.preventDefault();
      ex(m[e.key]);
    }
    if (e.key === "s") {
      e.preventDefault();
      copyHTML();
    }
  }
});
ct.addEventListener("paste", (e) => {
  if (!editing) return;
  e.preventDefault();
  const html = e.clipboardData.getData("text/html");
  const plain = e.clipboardData.getData("text/plain");
  ex(
    "insertHTML",
    html
      .replace(/ style="[^"]*mso[^"]*"/g, "")
      .replace(/<\/?[ow]:[^>]*>/g, "") || plain,
  );
});
// sec toggle in read mode
ct.addEventListener("click", (e) => {
  if (editing) return;
  const hd = e.target.closest(".sec-hd");
  if (hd) hd.parentElement.classList.toggle("open");
});

// ── SCROLL SPY ────────────────────────────────────────────────────
const obs = new IntersectionObserver(
  (entries) =>
    entries.forEach((e) => {
      const lnk = document.querySelector(`#tocl a[href="#${e.target.id}"]`);
      if (lnk) lnk.classList.toggle("active", e.isIntersecting);
    }),
  { rootMargin: "-10% 0px -80% 0px" },
);

// ── INIT ──────────────────────────────────────────────────────────
rebuildTOC();
setTimeout(() => {
  ct.querySelectorAll("h1,h2,h3").forEach((h) => {
    if (h.id) obs.observe(h);
  });
}, 400);
updateWC();
