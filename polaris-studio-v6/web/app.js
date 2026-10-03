const I18N = {
  en: {
    "nav.home": "Home",
    "nav.chat": "Chat",
    "nav.agent": "Agent",
    "nav.models": "Models",
    "nav.imagine": "Imagine",
    "nav.train": "Train",
    "nav.datasets": "Datasets",
    "nav.skills": "Skills",
    "nav.memory": "Memory",
    "nav.health": "Health",
    "nav.settings": "Settings",
    "hero.title": "Eight gigabytes, used on purpose.",
    "hero.body": "Polaris Studio v6 is a local workstation for an RX 590 GME. It reads each model's real context, clamps it to this card, and keeps image training alive when Windows or the driver would have killed it.",
    "act.train": "Train a LoRA",
    "act.models": "Read a model",
    "act.health": "Scan the machine",
    "act.drill": "Test the watchdog",
    "stat.vram": "VRAM budget",
    "stat.policy": "Context policy",
    "stat.skills": "Skills",
    "stat.jobs": "Live jobs",
    "assumed": "Assumed — card not visible in this process",
    "detected": "Detected",
    "empty.jobs": "No training runs yet.",
    "empty.chat": "Pick a model in Models, or start Ollama with Vulkan. Context is attached automatically on every message.",
    "send": "Send",
    "newChat": "New chat",
    "inspect": "Inspect",
    "activate": "Use this model",
    "copy": "Copy command",
    "upload": "Upload images",
    "sample": "Make sample shapes",
    "start": "Start training",
    "pause": "Pause",
    "stop": "Stop",
    "resume": "Resume",
    "save": "Save",
    "generate": "Generate",
    "search": "Search",
    "add": "Add",
    "onboard.title": "Set up this machine",
    "onboard.body": "Three checks, then the studio gets out of the way. Nothing here downloads a model.",
    "done": "Done",
    "next": "Continue",
  },
  fa: {
    "nav.home": "خانه",
    "nav.chat": "گفتگو",
    "nav.agent": "عامل",
    "nav.models": "مدل‌ها",
    "nav.imagine": "تصویر",
    "nav.train": "آموزش",
    "nav.datasets": "دیتاست",
    "nav.skills": "مهارت‌ها",
    "nav.memory": "حافظه",
    "nav.health": "سلامت",
    "nav.settings": "تنظیمات",
    "hero.title": "هشت گیگابایت، با حساب.",
    "hero.body": "استودیو پولاریس نسخه ۶ برای کارت RX 590 GME است. زمینهٔ هر مدل را از خود فایل می‌خواند، به حافظهٔ همین کارت محدود می‌کند، و آموزش تصویر را وقتی ویندوز یا درایور می‌خواهد بکشد، از چک‌پوینت ادامه می‌دهد.",
    "act.train": "آموزش LoRA",
    "act.models": "خواندن مدل",
    "act.health": "بررسی دستگاه",
    "act.drill": "آزمایش نگهبان",
    "stat.vram": "بودجهٔ VRAM",
    "stat.policy": "سیاست زمینه",
    "stat.skills": "مهارت‌ها",
    "stat.jobs": "کارهای فعال",
    "assumed": "فرض شده — کارت در این فرایند دیده نشد",
    "detected": "شناسایی شد",
    "empty.jobs": "هنوز آموزشی شروع نشده.",
    "empty.chat": "در بخش مدل‌ها یک مدل انتخاب کنید، یا Ollama را با Vulkan روشن کنید. زمینه روی هر پیام خودکار تنظیم می‌شود.",
    "send": "بفرست",
    "newChat": "گفتگوی تازه",
    "inspect": "بررسی",
    "activate": "استفاده از این مدل",
    "copy": "کپی دستور",
    "upload": "بارگذاری تصویر",
    "sample": "ساخت شکل‌های نمونه",
    "start": "شروع آموزش",
    "pause": "مکث",
    "stop": "توقف",
    "resume": "ادامه",
    "save": "ذخیره",
    "generate": "ساخت",
    "search": "جستجو",
    "add": "افزودن",
    "onboard.title": "آماده‌سازی همین دستگاه",
    "onboard.body": "سه بررسی، بعد استودیو کنار می‌رود. اینجا مدلی دانلود نمی‌شود.",
    "done": "تمام",
    "next": "ادامه",
  },
};

const NAV = ["home", "chat", "agent", "models", "imagine", "train", "datasets", "skills", "memory", "health", "settings"];
const state = {
  view: (location.hash || "#home").slice(1),
  lang: localStorage.getItem("polaris-lang") || "en",
  hardware: null,
  settings: null,
  health: null,
  jobs: [],
  datasets: [],
  skills: [],
  gallery: [],
  decisions: [],
  live: [],
  files: [],
  sessions: [],
  messages: [],
  sessionId: null,
  activeJob: null,
  dataset: null,
  skill: null,
  wizard: 0,
  streaming: "",
  trace: [],
};

function t(key) {
  return (I18N[state.lang] && I18N[state.lang][key]) || I18N.en[key] || key;
}
function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
}
function toast(message) {
  const node = document.createElement("div");
  node.className = "toast";
  node.textContent = message;
  document.getElementById("toasts").appendChild(node);
  setTimeout(() => node.remove(), 4200);
}
async function api(path, options = {}) {
  const res = await fetch(path, options);
  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = { error: text }; }
  if (!res.ok) throw new Error((data && data.error) || res.statusText);
  return data;
}
function applyLang() {
  document.documentElement.lang = state.lang;
  document.documentElement.dir = state.lang === "fa" ? "rtl" : "ltr";
  localStorage.setItem("polaris-lang", state.lang);
  const btn = document.getElementById("lang-btn");
  if (btn) btn.textContent = state.lang === "fa" ? "EN" : "فا";
}
function goto(view) {
  state.view = view;
  history.replaceState(null, "", "#" + view);
  render();
  if (view === "home" || view === "health") loadHealth();
  if (view === "models") loadModels();
  if (view === "datasets") loadDatasets();
  if (view === "skills") loadSkills();
  if (view === "memory") loadMemory();
  if (view === "imagine") loadGallery();
  if (view === "chat" || view === "agent") loadSessions();
  if (view === "train") loadTrain();
}

function rail() {
  const buttons = NAV.map((id) => `<button type="button" data-act="nav" data-view="${id}" class="${state.view === id ? "active" : ""}">${esc(t("nav." + id))}</button>`).join("");
  return `
    <div class="brand">
      <svg width="36" height="36" viewBox="0 0 64 64" aria-hidden="true"><path d="M32 4 L37 27 L60 32 L37 37 L32 60 L27 37 L4 32 L27 27 Z" fill="#e39a56"/></svg>
      <div><strong>POLARIS</strong><span>Studio v6</span></div>
    </div>
    <nav class="nav">${buttons}</nav>
    <div class="rail-foot">${esc(state.hardware ? state.hardware.gpu_name : "")}<br>${state.hardware ? state.hardware.vram_mb + " MB" : ""}</div>`;
}

function render() {
  applyLang();
  document.getElementById("rail").innerHTML = rail();
  document.getElementById("top-title").textContent = t("nav." + state.view) || "Studio";
  document.getElementById("eyebrow").textContent = state.hardware && state.hardware.assumed ? t("assumed") : t("detected");
  const pill = document.getElementById("gpu-pill");
  pill.textContent = state.hardware ? `${state.hardware.vram_mb} MB` : "GPU";
  pill.className = "pulse" + (state.hardware && state.hardware.assumed ? " warn" : "");
  const view = document.getElementById("view");
  const fn = VIEWS[state.view] || VIEWS.home;
  view.innerHTML = fn();
  drawChart();
}

const VIEWS = {
  home() {
    const hw = state.hardware || {};
    const jobs = state.jobs.slice(0, 4);
    const advice = (hw.advice || []).slice(0, 4).map((line) => `<li>${esc(line)}</li>`).join("");
    const jobList = jobs.length
      ? jobs.map((job) => `<li><strong>${esc(job.spec && job.spec.name || job.kind)}</strong> · ${esc(job.status)} · ${esc(job.message || "")}<div class="faint">${esc(job.step || 0)} / ${esc(job.total_steps || 0)}</div></li>`).join("")
      : `<li class="muted">${esc(t("empty.jobs"))}</li>`;
    return `
      <section class="grid two">
        <article class="card hero">
          <p class="eyebrow">v6 · ${esc(hw.profile && hw.profile.arch || "Polaris")}</p>
          <h2>${esc(t("hero.title"))}</h2>
          <p>${esc(t("hero.body"))}</p>
          <div class="row">
            <button class="solid" type="button" data-act="nav" data-view="train">${esc(t("act.train"))}</button>
            <button class="ghost" type="button" data-act="nav" data-view="models">${esc(t("act.models"))}</button>
            <button class="ghost" type="button" data-act="drill">${esc(t("act.drill"))}</button>
          </div>
        </article>
        <article class="card">
          <h3>${state.lang === "fa" ? "چرا قبلاً وسط کار می‌مرد" : "Why it used to die mid-run"}</h3>
          <ul class="list">
            <li>Windows TDR kills a long GPU step and leaves no Python error. The registry script raises it to 60s.</li>
            <li>DirectML fp16 on Polaris NaNs. Safe presets force fp32.</li>
            <li>A 4000px photo used to hit the VAE at full size. Images are cropped on CPU first.</li>
            <li>Ollama's default context is a few thousand tokens. Every request now sends the computed num_ctx.</li>
          </ul>
        </article>
      </section>
      <section class="grid stats" style="margin-top:16px">
        <article class="card stat"><span class="faint">${esc(t("stat.vram"))}</span><b>${esc(hw.vram_mb || "—")}</b><span class="muted">MB</span></article>
        <article class="card stat"><span class="faint">${esc(t("stat.policy"))}</span><b>${esc((state.settings && state.settings.context_policy) || "safe")}</b></article>
        <article class="card stat"><span class="faint">${esc(t("stat.skills"))}</span><b>${esc(state.skills.length || "—")}</b></article>
        <article class="card stat"><span class="faint">${esc(t("stat.jobs"))}</span><b>${esc(state.jobs.filter((j) => j.status === "running" || j.status === "recovering").length)}</b></article>
      </section>
      <section class="grid two" style="margin-top:16px">
        <article class="card"><h3>${state.lang === "fa" ? "نکته‌های همین کارت" : "Notes for this card"}</h3><ul class="list">${advice}</ul></article>
        <article class="card"><h3>${state.lang === "fa" ? "آخرین آموزش‌ها" : "Recent runs"}</h3><ul class="list">${jobList}</ul></article>
      </section>`;
  },
  chat() { return chatView(false); },
  agent() { return chatView(true); },
  models() {
    const decisions = (state.decisions || []).map((item) => `
      <tr>
        <td>${esc(item.display_name || item.model_key)}<div class="faint">${esc(item.native_source || "")}</div></td>
        <td>${esc(item.native_context)}</td>
        <td><strong>${esc(item.applied_context)}</strong></td>
        <td><span class="tag ${item.confidence === "high" ? "ok" : "warn"}">${esc(item.confidence)}</span></td>
        <td><button type="button" class="ghost" data-act="activate-model" data-key="${esc(item.model_key)}">${esc(t("activate"))}</button></td>
      </tr>`).join("");
    const active = (state.decisions || []).find((item) => item.model_key === (state.settings && state.settings.active_model));
    const budget = active && active.budget ? budgetBar(active.budget) : "";
    const command = active ? `<pre class="cmd">${esc(active.llama_command)}</pre><div class="row"><button type="button" class="ghost" data-act="copy" data-text="${esc(active.llama_command)}">${esc(t("copy"))}</button></div>` : `<p class="muted">${state.lang === "fa" ? "هنوز مدلی خوانده نشده." : "No model has been read yet."}</p>`;
    return `
      <section class="grid two">
        <article class="card">
          <h3>${state.lang === "fa" ? "دیدن مدل" : "See the model"}</h3>
          <p class="muted">${state.lang === "fa" ? "مسیر GGUF، پوشهٔ config.json، یا نام مدل Ollama. حدس از روی اسم با برچسب کم‌اطمینان می‌ماند." : "A GGUF path, a config.json folder, or an Ollama model name. A name guess stays labeled low confidence."}</p>
          <label>Model path or name</label>
          <input id="model-ref" placeholder="C:\\models\\qwen2.5-7b-instruct-q4_k_m.gguf" />
          <div class="row" style="margin-top:10px">
            <button class="solid" type="button" data-act="inspect">${esc(t("inspect"))}</button>
          </div>
          <div style="margin-top:16px">${budget}</div>
          ${command}
        </article>
        <article class="card">
          <h3>${state.lang === "fa" ? "زنده روی این دستگاه" : "Live on this machine"}</h3>
          <ul class="list">${(state.live || []).map((item) => `<li>${esc(item.backend)} · ${esc(item.name)} <button type="button" class="ghost" data-act="inspect-live" data-key="${esc(item.key)}">${esc(t("inspect"))}</button></li>`).join("") || `<li class="muted">Ollama / llama.cpp not answering.</li>`}</ul>
        </article>
      </section>
      <article class="card" style="margin-top:16px">
        <h3>${state.lang === "fa" ? "تصمیم‌های ذخیره‌شده" : "Stored decisions"}</h3>
        <table><thead><tr><th>Model</th><th>Native</th><th>Applied</th><th></th><th></th></tr></thead><tbody>${decisions || ""}</tbody></table>
      </article>`;
  },
  imagine() {
    const gallery = (state.gallery || []).map((item) => `<figure><img src="/api/gallery/${esc(item.id)}" alt=""><figcaption class="faint">${esc(item.prompt || "")}</figcaption></figure>`).join("");
    return `
      <section class="grid two">
        <article class="card">
          <h3>${state.lang === "fa" ? "ساخت تصویر" : "Generate"}</h3>
          <p class="muted">Forge / SD.Next on DirectML, port 7860. 512px. The studio will not invent a picture if the backend is down.</p>
          <label>Prompt</label><textarea id="gen-prompt" placeholder="a copper star on a dark workbench, 50mm, soft window light"></textarea>
          <label>Negative</label><input id="gen-neg" value="lowres, blurry, extra fingers, deformed, watermark, text" />
          <div class="grid stats">
            <div><label>W</label><input id="gen-w" value="512" /></div>
            <div><label>H</label><input id="gen-h" value="512" /></div>
            <div><label>Steps</label><input id="gen-steps" value="22" /></div>
            <div><label>Seed</label><input id="gen-seed" value="-1" /></div>
          </div>
          <label>LoRA name in Forge</label><input id="gen-lora" placeholder="my-concept" />
          <div class="row" style="margin-top:12px"><button class="solid" type="button" data-act="generate">${esc(t("generate"))}</button></div>
        </article>
        <article class="card">
          <h3>Gallery</h3>
          <div class="gallery">${gallery || `<p class="muted">Empty.</p>`}</div>
        </article>
      </section>`;
  },
  train() {
    if (state.activeJob && state.wizard === 99) return trainMonitor();
    const datasets = state.datasets || [];
    const options = datasets.map((item) => `<option value="${esc(item.id)}">${esc(item.name)} (${item.image_count})</option>`).join("");
    return `
      <div class="steps">
        ${["Project", "Data", "Model", "Review"].map((label, index) => `<span class="${state.wizard === index ? "on" : ""}">${index + 1} ${label}</span>`).join("")}
      </div>
      <article class="card">
        ${state.wizard === 0 ? `
          <h3>Name the concept</h3>
          <label>Project</label><input id="tr-name" value="my concept" />
          <label>Trigger word</label><input id="tr-trigger" placeholder="sks person" />
          <label>Preset</label>
          <select id="tr-preset">
            <option value="rx590-sd15-safe">RX 590 safe — 512, rank 8, fp32</option>
            <option value="rx590-sd15-quality">RX 590 quality — rank 16, more steps</option>
            <option value="rx590-sd15-fast">RX 590 fast draft</option>
          </select>` : ""}
        ${state.wizard === 1 ? `
          <h3>Dataset</h3>
          <label>Choose</label><select id="tr-dataset">${options || `<option value="">No datasets yet</option>`}</select>
          <div class="row" style="margin-top:10px"><button type="button" class="ghost" data-act="nav" data-view="datasets">Open datasets</button></div>` : ""}
        ${state.wizard === 2 ? `
          <h3>SD 1.5 base model</h3>
          <p class="muted">Local folder or a .safetensors file. SDXL is refused. Hugging Face ids work only if that machine can reach them.</p>
          <label>Path</label><input id="tr-base" placeholder="D:\\models\\v1-5-pruned.safetensors" />` : ""}
        ${state.wizard === 3 ? `
          <h3>Review</h3>
          <p>Preset <strong>${esc(state.trainDraft.preset || "")}</strong>, dataset <strong>${esc(state.trainDraft.dataset_id || "")}</strong>, base <strong>${esc(state.trainDraft.base_model || "")}</strong>.</p>
          <p class="muted">Batch stays 1. Watchdog checkpoints every 25 steps, resumes on OOM, NaN, and driver reset, and will not loop the same crashing step forever.</p>` : ""}
        <div class="row" style="margin-top:16px">
          ${state.wizard > 0 ? `<button type="button" class="ghost" data-act="wiz-back">Back</button>` : ""}
          ${state.wizard < 3 ? `<button type="button" class="solid" data-act="wiz-next">${esc(t("next"))}</button>` : `<button type="button" class="solid" data-act="train-start">${esc(t("start"))}</button>`}
        </div>
      </article>`;
  },
  datasets() {
    const cards = (state.datasets || []).map((item) => `<button type="button" class="item" data-act="open-dataset" data-id="${esc(item.id)}"><strong>${esc(item.name)}</strong><div class="faint">${item.image_count} images · ${esc(item.trigger || "")}</div></button>`).join("");
    const detail = state.dataset ? datasetDetail(state.dataset) : `<p class="muted">Select a dataset or make the sample shapes. Those shapes only prove the pipeline.</p>`;
    return `
      <div class="row" style="margin-bottom:12px">
        <input id="ds-name" placeholder="Dataset name" style="max-width:220px" />
        <input id="ds-trigger" placeholder="Trigger" style="max-width:180px" />
        <button type="button" class="solid" data-act="create-dataset">${esc(t("add"))}</button>
        <button type="button" class="ghost" data-act="sample">${esc(t("sample"))}</button>
      </div>
      <section class="split">
        <div class="list">${cards || `<div class="item muted">Empty.</div>`}</div>
        <article class="card">${detail}</article>
      </section>`;
  },
  skills() {
    const list = (state.skills || []).map((skill) => `
      <button type="button" class="item" data-act="open-skill" data-name="${esc(skill.name)}">
        <strong>${esc(skill.name)}</strong> <span class="tag">${esc(skill.category || skill.origin || "")}</span>
        <div class="faint">${esc(skill.description || skill.error || "")}</div>
      </button>`).join("");
    const body = state.skill ? `<h3>${esc(state.skill.name)}</h3><p class="muted">${esc(state.skill.description)}</p><pre class="cmd">${esc(state.skill.body || "")}</pre>` : `<p class="muted">Skills are loaded by name only until the agent calls skill_view. Same SKILL.md layout as Hermes.</p>`;
    return `<section class="split"><div class="list">${list}</div><article class="card">${body}</article></section>`;
  },
  memory() {
    const items = (state.memories || []).map((item) => `<li>${esc(item.content)} <button type="button" class="ghost" data-act="forget" data-id="${item.id}">×</button></li>`).join("");
    return `
      <article class="card">
        <label>${state.lang === "fa" ? "یادداشت پایدار" : "Durable note"}</label>
        <textarea id="mem-text" placeholder="Base model lives at D:\\models\\v1-5. Do not train SDXL."></textarea>
        <div class="row" style="margin-top:8px"><button type="button" class="solid" data-act="add-memory">${esc(t("add"))}</button></div>
        <ul class="list" style="margin-top:14px">${items || `<li class="muted">Empty.</li>`}</ul>
      </article>`;
  },
  health() {
    const report = state.health;
    if (!report) return `<article class="card">Scanning…</article>`;
    const rows = (report.checks || []).map((item) => `<tr><td class="check-${esc(item.level)}">${esc(item.level)}</td><td>${esc(item.name)}</td><td>${esc(item.message)}<div class="faint">${esc(item.fix || "")}</div></td></tr>`).join("");
    return `
      <article class="card">
        <h2 style="font-family:var(--serif);font-weight:500">${esc(report.score)}</h2>
        <p class="muted">${state.lang === "fa" ? "نمره از روی خطا و هشدار است، نه یک عدد تزئینی." : "Score drops for errors and warnings. It is not decoration."}</p>
        <table><tbody>${rows}</tbody></table>
        <div class="row" style="margin-top:12px"><button type="button" class="ghost" data-act="rescan">${esc(t("act.health"))}</button><button type="button" class="ghost" data-act="drill">${esc(t("act.drill"))}</button></div>
      </article>`;
  },
  settings() {
    const s = state.settings || {};
    const field = (id, label, value, type = "text") => `<label>${label}</label><input id="${id}" type="${type}" value="${esc(value ?? "")}" />`;
    return `
      <article class="card">
        ${field("set-ollama", "Ollama URL", s.ollama_url)}
        ${field("set-llama", "llama.cpp URL", s.llamacpp_url)}
        ${field("set-openai", "OpenAI-compatible base", s.openai_base)}
        ${field("set-forge", "Forge / A1111 URL", s.forge_url)}
        ${field("set-comfy", "ComfyUI URL", s.comfy_url)}
        ${field("set-vision", "Vision model for captions", s.vision_model)}
        <label>Context policy</label>
        <select id="set-policy">
          ${["safe", "max_fit", "native"].map((item) => `<option ${s.context_policy === item ? "selected" : ""}>${item}</option>`).join("")}
        </select>
        ${field("set-vram", "VRAM override MB (0 = auto)", s.vram_override_mb, "number")}
        ${field("set-stall", "Stall timeout seconds", s.stall_seconds, "number")}
        <label><input type="checkbox" id="set-terminal" ${s.terminal_enabled ? "checked" : ""} style="width:auto" /> Agent terminal tool</label>
        <label><input type="checkbox" id="set-native" ${s.native_tool_calls ? "checked" : ""} style="width:auto" /> Native tool calls (stronger models only)</label>
        <div class="row" style="margin-top:12px">
          <button type="button" class="solid" data-act="save-settings">${esc(t("save"))}</button>
          <a class="ghost" href="/api/backup">Backup zip</a>
        </div>
      </article>`;
  },
};

function budgetBar(budget) {
  const total = budget.vram_mb || 1;
  const parts = [
    ["d", budget.driver_mb, "driver"],
    ["c", budget.compute_mb, "compute"],
    ["w", budget.weights_mb, "weights"],
    ["k", budget.kv_mb, "kv"],
  ];
  const spans = parts.map(([cls, mb]) => `<span class="${cls}" style="width:${Math.max(0, (mb / total) * 100)}%"></span>`).join("");
  return `<div class="bar" title="driver / compute / weights / kv">${spans}</div><p class="faint">driver ${budget.driver_mb} · compute ${budget.compute_mb} · weights ${budget.weights_mb} · kv ${budget.kv_mb} · free ${budget.free_mb} MB</p>`;
}

function chatView(agent) {
  const sessions = (state.sessions || []).map((item) => `<button type="button" class="item" data-act="open-session" data-id="${esc(item.id)}" data-agent="${agent ? "1" : "0"}">${esc(item.title)}</button>`).join("");
  const bubbles = (state.messages || []).map((item) => `<div class="bubble ${esc(item.role)}">${esc(item.content)}</div>`).join("");
  const live = state.streaming ? `<div class="bubble assistant">${esc(state.streaming)}</div>` : "";
  const trace = (state.trace || []).map((item) => `<div class="trace">${esc(item.name || item.type)} ${esc(JSON.stringify(item.arguments || item.result || item.message || "").slice(0, 500))}</div>`).join("");
  return `
    <section class="chat-layout">
      <div>
        <button type="button" class="ghost" data-act="new-session" data-agent="${agent ? "1" : "0"}">${esc(t("newChat"))}</button>
        <div class="list" style="margin-top:10px">${sessions}</div>
      </div>
      <article class="card">
        <div class="messages" id="messages">${bubbles}${live}${trace || `<p class="muted">${esc(t("empty.chat"))}</p>`}</div>
        <div class="composer">
          <textarea id="composer" placeholder="${agent ? "/lora-training how should I start" : "Ask the model"}"></textarea>
          <button type="button" class="solid" data-act="send" data-agent="${agent ? "1" : "0"}">${esc(t("send"))}</button>
        </div>
      </article>
    </section>`;
}

function trainMonitor() {
  const job = state.activeJob || {};
  const recoveries = (job.recoveries || []).map((item) => `<li>step ${esc(item.step)} · ${esc(item.reason)}</li>`).join("");
  return `
    <article class="card">
      <div class="row">
        <span class="tag ${job.status === "failed" ? "bad" : job.status === "completed" ? "ok" : "warn"}">${esc(job.status)}</span>
        <strong>${esc(job.message || "")}</strong>
      </div>
      <p class="stat"><b>${esc(job.step || 0)}</b> <span class="muted">/ ${esc(job.total_steps || 0)} · loss ${esc(job.loss ?? "—")} · attempt ${esc(job.attempt || 1)}</span></p>
      <canvas class="chart" id="loss-chart" width="800" height="180"></canvas>
      <div class="row" style="margin-top:12px">
        <button type="button" class="ghost" data-act="pause-job" data-id="${esc(job.id)}">${esc(t("pause"))}</button>
        <button type="button" class="danger" data-act="stop-job" data-id="${esc(job.id)}">${esc(t("stop"))}</button>
        <button type="button" class="ghost" data-act="resume-job" data-id="${esc(job.id)}">${esc(t("resume"))}</button>
        <button type="button" class="ghost" data-act="log-job" data-id="${esc(job.id)}">Log</button>
      </div>
      <h3>Recoveries</h3>
      <ul class="list">${recoveries || `<li class="muted">None yet. A clean run stays empty.</li>`}</ul>
    </article>`;
}

function datasetDetail(dataset) {
  const thumbs = (dataset.images || []).map((item) => `
    <div class="thumb">
      <img src="/api/datasets/${esc(dataset.id)}/images/${esc(item.name)}" alt="">
      <textarea data-caption="${esc(item.name)}">${esc(item.caption || "")}</textarea>
      <button type="button" class="ghost" data-act="save-caption" data-id="${esc(dataset.id)}" data-name="${esc(item.name)}">${esc(t("save"))}</button>
    </div>`).join("");
  const issues = (dataset.issues || []).map((item) => `<li class="${item.level === "error" ? "check-error" : "check-warn"}">${esc(item.name)} ${esc(item.message)}</li>`).join("");
  return `
    <h3>${esc(dataset.name)}</h3>
    <p class="faint">${esc(dataset_path_hint(dataset.id))}</p>
    <div class="row">
      <input type="file" id="ds-files" accept="image/*" multiple />
      <button type="button" class="solid" data-act="upload" data-id="${esc(dataset.id)}">${esc(t("upload"))}</button>
    </div>
    <ul class="list">${issues}</ul>
    <div class="thumbs" style="margin-top:12px">${thumbs}</div>`;
}
function dataset_path_hint(id) {
  return "Folder is under the studio data/datasets/" + id + "/images — dropping files there is picked up on refresh.";
}

function drawChart() {
  const canvas = document.getElementById("loss-chart");
  if (!canvas || !state.activeJob) return;
  const ctx = canvas.getContext("2d");
  const history = state.activeJob.history || [];
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = "#2a3128";
  ctx.strokeRect(0, 0, canvas.width, canvas.height);
  if (history.length < 2) return;
  const losses = history.map((item) => item.loss);
  const min = Math.min(...losses);
  const max = Math.max(...losses);
  ctx.beginPath();
  ctx.strokeStyle = "#e39a56";
  ctx.lineWidth = 2;
  history.forEach((item, index) => {
    const x = (index / (history.length - 1)) * (canvas.width - 16) + 8;
    const y = 12 + ((max - item.loss) / (max - min || 1)) * (canvas.height - 24);
    if (index === 0) ctx.moveTo(x, y);
    else ctx.lineTo(x, y);
  });
  ctx.stroke();
}

async function loadHealth() {
  try {
    state.health = await api("/api/health");
    state.hardware = state.health.hardware;
    if (state.view === "home" || state.view === "health") render();
  } catch (err) { toast(err.message); }
}
async function loadModels() {
  try {
    const data = await api("/api/llm/models");
    state.live = data.live || [];
    state.files = data.files || [];
    state.decisions = data.decisions || [];
    if (state.view === "models") render();
  } catch (err) { toast(err.message); }
}
async function loadDatasets() {
  state.datasets = await api("/api/datasets");
  if (state.view === "datasets" || state.view === "train") render();
}
async function loadSkills() {
  state.skills = await api("/api/skills");
  if (state.view === "skills" || state.view === "home") render();
}
async function loadMemory() {
  state.memories = await api("/api/memory");
  if (state.view === "memory") render();
}
async function loadGallery() {
  state.gallery = await api("/api/gallery");
  if (state.view === "imagine") render();
}
async function loadSessions() {
  state.sessions = await api("/api/sessions?kind=" + (state.view === "agent" ? "agent" : "chat"));
  if (state.view === "chat" || state.view === "agent") render();
}
async function loadTrain() {
  state.jobs = await api("/api/train/jobs");
  state.datasets = await api("/api/datasets");
  if (state.activeJob) {
    try { state.activeJob = await api("/api/train/jobs/" + state.activeJob.id); } catch { /* gone */ }
  }
  if (state.view === "train" || state.view === "home") render();
}

function readTrainDraft() {
  state.trainDraft = state.trainDraft || {};
  const take = (id, key) => {
    const node = document.getElementById(id);
    if (node) state.trainDraft[key] = node.value;
  };
  take("tr-name", "name");
  take("tr-trigger", "trigger");
  take("tr-preset", "preset");
  take("tr-dataset", "dataset_id");
  take("tr-base", "base_model");
}

async function send(agent) {
  const box = document.getElementById("composer");
  const content = (box && box.value || "").trim();
  if (!content) return;
  box.value = "";
  state.streaming = "";
  state.trace = [];
  state.messages = state.messages.concat([{ role: "user", content }]);
  render();
  const res = await fetch(agent ? "/api/agent" : "/api/chat", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ content, session_id: state.sessionId, model: state.settings && state.settings.active_model }),
  });
  if (!res.ok || !res.body) {
    toast("Chat failed");
    return;
  }
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";
  while (true) {
    const chunk = await reader.read();
    if (chunk.done) break;
    buffer += decoder.decode(chunk.value, { stream: true });
    const parts = buffer.split("\n\n");
    buffer = parts.pop();
    for (const part of parts) {
      const line = part.split("\n").find((item) => item.startsWith("data:"));
      if (!line) continue;
      let event;
      try { event = JSON.parse(line.slice(5).trim()); } catch { continue; }
      if (event.type === "session") state.sessionId = event.session_id;
      if (event.type === "token") state.streaming += event.text || "";
      if (event.type === "tool" || event.type === "tool_result" || event.type === "error" || event.type === "context") state.trace.push(event);
      if (event.type === "error") toast(event.message || "error");
      if (event.type === "done") {
        state.messages.push({ role: "assistant", content: state.streaming || event.text || "" });
        state.streaming = "";
      }
      const boxEl = document.getElementById("messages");
      if (boxEl && (state.view === "chat" || state.view === "agent")) {
        boxEl.innerHTML = state.messages.map((item) => `<div class="bubble ${esc(item.role)}">${esc(item.content)}</div>`).join("")
          + (state.streaming ? `<div class="bubble assistant">${esc(state.streaming)}</div>` : "")
          + state.trace.map((item) => `<div class="trace">${esc(item.type)} ${esc(item.name || "")} ${esc((item.message || JSON.stringify(item.result || item.arguments || "")).slice(0, 400))}</div>`).join("");
        boxEl.scrollTop = boxEl.scrollHeight;
      }
    }
  }
}

function showModal(html) {
  const modal = document.getElementById("modal");
  modal.classList.remove("hidden");
  modal.innerHTML = `<div class="sheet">${html}<div class="row" style="margin-top:12px"><button type="button" class="ghost" data-act="close-modal">Close</button></div></div>`;
}

function onboard() {
  showModal(`
    <p class="eyebrow">v6</p>
    <h2>${esc(t("onboard.title"))}</h2>
    <p>${esc(t("onboard.body"))}</p>
    <label>Language</label>
    <select id="ob-lang"><option value="en">English</option><option value="fa">فارسی</option></select>
    <label>VRAM if Windows reports 4GB on an 8GB card</label>
    <select id="ob-vram"><option value="0">Auto / 8192 for RX 590 GME</option><option value="8192">8192</option><option value="4096">4096 (RX 570 / 580 4GB)</option></select>
    <label>Forge URL</label>
    <input id="ob-forge" value="http://127.0.0.1:7860" />
    <button type="button" class="solid" data-act="finish-onboard" style="margin-top:12px">${esc(t("done"))}</button>`);
}

document.body.addEventListener("click", async (event) => {
  const node = event.target.closest("[data-act]");
  if (!node) return;
  const act = node.dataset.act;
  try {
    if (act === "nav") goto(node.dataset.view);
    else if (act === "lang") {
      state.lang = state.lang === "fa" ? "en" : "fa";
      if (state.settings) api("/api/settings", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ language: state.lang }) });
      render();
    } else if (act === "palette") showModal(`<h3>Go</h3><div class="list">${NAV.map((id) => `<button type="button" class="item" data-act="nav" data-view="${id}">${esc(t("nav." + id))}</button>`).join("")}</div>`);
    else if (act === "close-modal") document.getElementById("modal").classList.add("hidden");
    else if (act === "drill") {
      const job = await api("/api/train/drill", { method: "POST" });
      state.activeJob = job;
      state.wizard = 99;
      toast("Watchdog drill started");
      goto("train");
    } else if (act === "inspect" || act === "inspect-live") {
      const ref = act === "inspect" ? document.getElementById("model-ref").value : node.dataset.key;
      const decision = await api("/api/models/inspect", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ ref, activate: true }) });
      state.settings.active_model = decision.model_key;
      toast(`Context ${decision.applied_context} (${decision.confidence})`);
      await loadModels();
    } else if (act === "activate-model") {
      await api("/api/models/activate", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ model_key: node.dataset.key }) });
      state.settings.active_model = node.dataset.key;
      toast("Active model set. Chat will send its context.");
    } else if (act === "copy") {
      await navigator.clipboard.writeText(node.dataset.text || "");
      toast("Copied");
    } else if (act === "generate") {
      const payload = {
        prompt: document.getElementById("gen-prompt").value,
        negative: document.getElementById("gen-neg").value,
        width: Number(document.getElementById("gen-w").value),
        height: Number(document.getElementById("gen-h").value),
        steps: Number(document.getElementById("gen-steps").value),
        seed: Number(document.getElementById("gen-seed").value),
        lora: document.getElementById("gen-lora").value,
      };
      toast("Waiting on the image backend…");
      await api("/api/generate", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      await loadGallery();
    } else if (act === "wiz-next") {
      readTrainDraft();
      state.wizard = Math.min(3, state.wizard + 1);
      render();
    } else if (act === "wiz-back") {
      state.wizard = Math.max(0, state.wizard - 1);
      render();
    } else if (act === "train-start") {
      readTrainDraft();
      const job = await api("/api/train/start", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(state.trainDraft) });
      state.activeJob = job;
      state.wizard = 99;
      render();
    } else if (act === "pause-job") await api(`/api/train/jobs/${node.dataset.id}/pause`, { method: "POST" });
    else if (act === "stop-job") await api(`/api/train/jobs/${node.dataset.id}/stop`, { method: "POST" });
    else if (act === "resume-job") await api(`/api/train/jobs/${node.dataset.id}/resume`, { method: "POST" });
    else if (act === "log-job") {
      const data = await api(`/api/train/jobs/${node.dataset.id}/log`);
      showModal(`<pre class="cmd">${esc(data.log || "empty")}</pre>`);
    } else if (act === "create-dataset") {
      await api("/api/datasets", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ name: document.getElementById("ds-name").value, trigger: document.getElementById("ds-trigger").value }) });
      await loadDatasets();
    } else if (act === "sample") {
      state.dataset = await api("/api/datasets/sample", { method: "POST" });
      await loadDatasets();
    } else if (act === "open-dataset") {
      state.dataset = await api("/api/datasets/" + node.dataset.id);
      render();
    } else if (act === "upload") {
      const input = document.getElementById("ds-files");
      if (!input.files.length) return toast("Choose files first");
      for (const file of input.files) {
        const body = new FormData();
        body.append("file", file);
        const res = await fetch(`/api/datasets/${node.dataset.id}/upload`, { method: "POST", body });
        if (!res.ok) toast(await res.text());
      }
      state.dataset = await api("/api/datasets/" + node.dataset.id);
      render();
    } else if (act === "save-caption") {
      const area = document.querySelector(`textarea[data-caption="${CSS.escape(node.dataset.name)}"]`);
      await api(`/api/datasets/${node.dataset.id}/caption`, { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ name: node.dataset.name, caption: area.value }) });
      toast("Caption saved");
    } else if (act === "open-skill") {
      state.skill = await api("/api/skills/" + node.dataset.name);
      render();
    } else if (act === "add-memory") {
      await api("/api/memory", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ content: document.getElementById("mem-text").value }) });
      await loadMemory();
    } else if (act === "forget") {
      await api("/api/memory/" + node.dataset.id, { method: "DELETE" });
      await loadMemory();
    } else if (act === "rescan") await loadHealth();
    else if (act === "save-settings") {
      const payload = {
        ollama_url: document.getElementById("set-ollama").value,
        llamacpp_url: document.getElementById("set-llama").value,
        openai_base: document.getElementById("set-openai").value,
        forge_url: document.getElementById("set-forge").value,
        comfy_url: document.getElementById("set-comfy").value,
        vision_model: document.getElementById("set-vision").value,
        context_policy: document.getElementById("set-policy").value,
        vram_override_mb: Number(document.getElementById("set-vram").value || 0),
        stall_seconds: Number(document.getElementById("set-stall").value || 180),
        terminal_enabled: document.getElementById("set-terminal").checked,
        native_tool_calls: document.getElementById("set-native").checked,
        language: state.lang,
      };
      state.settings = await api("/api/settings", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      toast("Saved");
    } else if (act === "new-session") {
      const session = await api("/api/sessions", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ kind: node.dataset.agent === "1" ? "agent" : "chat" }) });
      state.sessionId = session.id;
      state.messages = [];
      await loadSessions();
    } else if (act === "open-session") {
      const detail = await api("/api/sessions/" + node.dataset.id);
      state.sessionId = detail.id;
      state.messages = detail.messages || [];
      render();
    } else if (act === "send") await send(node.dataset.agent === "1");
    else if (act === "finish-onboard") {
      state.lang = document.getElementById("ob-lang").value;
      state.settings = await api("/api/settings", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ language: state.lang, vram_override_mb: Number(document.getElementById("ob-vram").value), forge_url: document.getElementById("ob-forge").value, first_run: false }) });
      document.getElementById("modal").classList.add("hidden");
      render();
    }
  } catch (err) {
    toast(err.message || String(err));
  }
});

document.addEventListener("keydown", (event) => {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
    event.preventDefault();
    showModal(`<h3>Go</h3><div class="list">${NAV.map((id) => `<button type="button" class="item" data-act="nav" data-view="${id}">${esc(t("nav." + id))}</button>`).join("")}</div>`);
  }
});

async function boot() {
  try {
    state.settings = await api("/api/settings");
    state.lang = state.settings.language || state.lang;
  } catch (err) {
    document.getElementById("view").innerHTML = `<article class="card"><h2>API did not start</h2><p>${esc(err.message)}</p></article>`;
    return;
  }
  state.trainDraft = { preset: "rx590-sd15-safe" };
  await loadSkills();
  await loadHealth();
  state.jobs = await api("/api/train/jobs").catch(() => []);
  render();
  if (state.settings.first_run) onboard();
  setInterval(async () => {
    if (state.view !== "train" && state.view !== "home") return;
    try {
      state.jobs = await api("/api/train/jobs");
      if (state.activeJob) state.activeJob = await api("/api/train/jobs/" + state.activeJob.id);
      if (state.view === "train" && state.wizard === 99) {
        const card = document.querySelector("#view .card");
        if (card) {
          const fresh = trainMonitor();
          document.getElementById("view").innerHTML = fresh;
          drawChart();
        }
      }
    } catch { /* ignore poll errors */ }
  }, 2000);
}

boot();
