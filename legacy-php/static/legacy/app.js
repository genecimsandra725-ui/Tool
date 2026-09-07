(function () {
  "use strict";

  const data = window.TOOL_DATA || { meta: {}, tools: [] };
  const meta = data.meta || {};
  const tools = Array.isArray(data.tools) ? data.tools : [];

  const byId = (id) => document.getElementById(id);

  const searchInput = byId("searchInput");
  const clearBtn = byId("clearSearch");
  const quickGrid = byId("quickGrid");
  const toolGrid = byId("toolGrid");
  const resultCount = byId("resultCount");
  const emptyState = byId("emptyState");
  const endingEl = byId("ending");
  const introCopy = byId("introCopy");
  const introIcon = byId("introIcon");
  const toolsTitle = byId("toolsTitle");
  const sourceLink = byId("sourceLink");

  function safeUrl(value) {
    if (!value) return "";
    try {
      const parsed = new URL(value, window.location.href);
      return parsed.protocol === "http:" || parsed.protocol === "https:"
        ? parsed.href
        : "";
    } catch (_error) {
      return "";
    }
  }

  function renderMeta() {
    document.title = meta.title || "阿旻同学 | 工具箱";
    sourceLink.href = safeUrl(meta.source) || "#";

    if (meta.introIcon) {
      introIcon.textContent = meta.introIcon;
    } else {
      introIcon.hidden = true;
    }

    if (Array.isArray(meta.intro) && meta.intro.length) {
      const fragment = document.createDocumentFragment();
      meta.intro.forEach((paragraph) => {
        const p = document.createElement("p");
        p.textContent = paragraph;
        fragment.appendChild(p);
      });
      introCopy.replaceChildren(fragment);
    } else {
      byId("introIcon").closest(".intro-block").hidden = true;
    }

    if (meta.sectionTitle) {
      const icon = meta.sectionIcon ? meta.sectionIcon + " " : "";
      toolsTitle.textContent = icon + meta.sectionTitle;
    }
  }

  function renderQuickLinks() {
    const items = Array.isArray(meta.quick) ? meta.quick : [];
    const fragment = document.createDocumentFragment();

    items.forEach((item) => {
      const href = safeUrl(item.href);
      if (!href) return;

      const card = document.createElement("a");
      card.className = "quick-card";
      card.href = href;
      card.target = "_blank";
      card.rel = "noopener noreferrer";

      const icon = document.createElement("span");
      icon.className = "quick-icon";
      icon.textContent = item.icon || item.label.slice(0, 1);

      const title = document.createElement("span");
      title.className = "quick-title";
      title.textContent = item.label;

      const arrow = document.createElement("span");
      arrow.className = "quick-arrow";
      arrow.textContent = "↗";
      arrow.setAttribute("aria-hidden", "true");

      card.append(icon, title, arrow);
      fragment.appendChild(card);
    });

    quickGrid.replaceChildren(fragment);
  }

  function appendToolSegments(card, item) {
    const copy = document.createElement("div");
    copy.className = "tool-copy";

    let sawLink = false;
    item.segments.forEach((segment) => {
      if (segment.t === "l") {
        const href = safeUrl(segment.u);
        if (!href) {
          if (segment.x) copy.appendChild(document.createTextNode(segment.x));
          return;
        }
        const link = document.createElement("a");
        link.className = sawLink ? "tool-link" : "tool-link tool-title-link";
        link.href = href;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = segment.x;
        copy.appendChild(link);
        sawLink = true;
        return;
      }

      if (segment.x) copy.appendChild(document.createTextNode(segment.x));
    });

    card.appendChild(copy);
  }

  function renderTools(list) {
    const fragment = document.createDocumentFragment();

    list.forEach((item) => {
      const card = document.createElement("article");
      card.className = "tool-card";
      appendToolSegments(card, item);
      fragment.appendChild(card);
    });

    toolGrid.replaceChildren(fragment);
    resultCount.textContent = list.length + " / " + tools.length;
    emptyState.hidden = list.length !== 0;
    endingEl.hidden = Boolean(searchInput.value.trim()) || !meta.ending;
    endingEl.textContent = meta.ending || "";
  }

  function applyFilter() {
    const query = searchInput.value.trim().toLowerCase();
    clearBtn.hidden = query.length === 0;

    let list = tools;
    if (query) {
      list = tools.filter((item) => {
        const haystack = [
          item.title,
          item.text,
          item.url,
          ...item.links.map((link) => link.label + " " + link.href),
        ]
          .join(" ")
          .toLowerCase();
        return haystack.includes(query);
      });
    }

    renderTools(list);
  }

  searchInput.addEventListener("input", applyFilter);
  clearBtn.addEventListener("click", () => {
    searchInput.value = "";
    searchInput.focus();
    applyFilter();
  });

  renderMeta();
  renderQuickLinks();
  applyFilter();
})();
