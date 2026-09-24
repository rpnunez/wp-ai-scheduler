/**
 * PR Triage Command Center - Main Application Controller
 * Pure Controller: state management, sorting, filtering, caching, and API dispatch.
 * View rendering is fully delegated to the Templates registry.
 */
class App {
  constructor() {
    this.api = new GitHubApi();
    this.cache = new CacheStore(this.api);

    this.activeRepo = localStorage.getItem(AppConfig.storageKeys.activeRepo) || AppConfig.defaultRepo;
    this.currentHubFilter = "all";
    this.activeRiskFilters = new Set();
    this.activeAuthorFilters = new Set();
    this.activeTagFilter = null;
    this.dateRangeFilter = "all";

    this.sortColumn = "dates";
    this.sortDirection = "desc";

    this.collapsedHubs = JSON.parse(localStorage.getItem(AppConfig.storageKeys.collapsedHubs) || "{}");
    this.prs = [];
    this.duplicateGroups = [];
    this.isSyncing = false;
    this.pendingActionPR = null;

    this.loadFromCache();
    this.init();
  }

  parseRepo(repoStr) {
    const parts = (repoStr || "").split("/");
    return [parts[0] || "rpnunez", parts[1] || "wp-ai-scheduler"];
  }

  init() {
    const savedTheme = localStorage.getItem(AppConfig.storageKeys.theme) || "dark";
    document.body.setAttribute("data-theme", savedTheme);
    this.updateThemeButton();

    window.onRateLimitUpdate = (info) => this.renderRateLimit(info);
    window.onLogEntry = () => this.updateLogCounter();

    if (this.api.hasToken()) {
      this.api.getRateLimit().catch(e => console.warn("Rate limit check failed:", e));
      this.populateUserRepos();
    }
  }

  updateThemeButton() {
    const btn = document.getElementById("themeToggleBtn");
    if (!btn) return;
    const isDark = document.body.getAttribute("data-theme") === "dark";
    btn.innerText = isDark ? "☀️ Light Mode" : "🌙 Dark Mode";
  }

  toggleTheme() {
    const current = document.body.getAttribute("data-theme");
    const next = current === "dark" ? "light" : "dark";
    document.body.setAttribute("data-theme", next);
    localStorage.setItem(AppConfig.storageKeys.theme, next);
    this.updateThemeButton();
    if (window.logger) window.logger.info(`Theme switched to ${next}`, { component: "UI" });
  }

  renderRateLimit(info) {
    const el = document.getElementById("rateLimitInfo");
    if (el && info) {
      el.innerHTML = `API Rate Limit: <strong>${info.remaining}</strong> / ${info.limit}`;
    }
  }

  updateLogCounter() {
    const btn = document.getElementById("logBtnBadge");
    if (btn && window.logger) {
      btn.innerText = window.logger.getLogs().length;
    }
  }

  loadFromCache() {
    const [owner, repo] = this.parseRepo(this.activeRepo);
    const cached = this.cache.getCache(owner, repo);
    const rawPRs = Object.values(cached.prs || {});

    if (rawPRs.length > 0) {
      const enriched = rawPRs.map(p => Classifier.enrichPR(p));
      const dupInfo = DuplicateClustering.clusterPRs(enriched);
      this.duplicateGroups = dupInfo.duplicateGroups;
      this.prs = DuplicateClustering.assignHubs(enriched, dupInfo);
    } else {
      this.prs = [];
      this.duplicateGroups = [];
    }

    this.renderKPIs();
    this.renderAll();

    const syncLabel = document.getElementById("lastSyncLabel");
    if (syncLabel) {
      syncLabel.innerText = cached.lastSync ? `Last synced: ${new Date(cached.lastSync).toLocaleTimeString()}` : "Not synced yet";
    }
  }

  openSyncLiveModal() {
    const modal = document.getElementById("syncLiveModal");
    const container = document.getElementById("syncLiveLogContent");
    const statusBadge = document.getElementById("syncLiveStatusBadge");
    const summaryText = document.getElementById("syncLiveSummaryText");
    const doneBtn = document.getElementById("syncLiveDoneBtn");
    const bar = document.getElementById("syncLiveProgressBar");

    if (container) container.innerHTML = "";
    if (statusBadge) {
      statusBadge.className = "badge badge-blue";
      statusBadge.innerText = "Syncing...";
    }
    if (summaryText) summaryText.innerText = `Connecting to GitHub API for ${this.activeRepo}...`;
    if (doneBtn) doneBtn.style.display = "none";
    if (bar) bar.style.width = "10%";
    if (modal) modal.classList.add("open");
  }

  closeSyncLiveModal() {
    const modal = document.getElementById("syncLiveModal");
    if (modal) modal.classList.remove("open");
  }

  appendLiveSyncLog(entry) {
    const container = document.getElementById("syncLiveLogContent");
    if (!container) return;
    const row = document.createElement("div");
    row.innerHTML = Templates.logRow(entry);
    container.appendChild(row.firstElementChild);
    container.scrollTop = container.scrollHeight;
  }

  async syncAll() {
    if (this.isSyncing) return;
    this.isSyncing = true;
    const [owner, repo] = this.parseRepo(this.activeRepo);

    this.openSyncLiveModal();
    this.showProgress(true, "Syncing PRs with GitHub...");

    const originalLogHook = window.onLogEntry;
    window.onLogEntry = (entry) => {
      this.updateLogCounter();
      if (entry) this.appendLiveSyncLog(entry);
    };

    if (window.logger) window.logger.info(`Starting synchronization for ${owner}/${repo}...`, { component: "App" });

    try {
      await this.cache.syncRepository(owner, repo, (prog) => {
        const bar = document.getElementById("syncLiveProgressBar");
        const summaryText = document.getElementById("syncLiveSummaryText");
        if (prog.total && prog.completed) {
          const pct = Math.round((prog.completed / prog.total) * 100);
          this.updateProgress(pct, prog.message);
          if (bar) bar.style.width = `${pct}%`;
        } else {
          this.updateProgress(null, prog.message);
        }
        if (summaryText && prog.message) summaryText.innerText = prog.message;
      });

      this.loadFromCache();
      
      const statusBadge = document.getElementById("syncLiveStatusBadge");
      const summaryText = document.getElementById("syncLiveSummaryText");
      const doneBtn = document.getElementById("syncLiveDoneBtn");
      const bar = document.getElementById("syncLiveProgressBar");

      if (statusBadge) {
        statusBadge.className = "badge badge-green";
        statusBadge.innerText = "Complete";
      }
      if (bar) bar.style.width = "100%";
      if (summaryText) summaryText.innerText = `✅ Successfully synchronized ${this.prs.length} pull requests.`;
      if (doneBtn) doneBtn.style.display = "inline-flex";

      this.showToast("Sync complete!");
    } catch (err) {
      if (window.logger) window.logger.error(`Sync failed: ${err.message}`, { component: "App" });
      const statusBadge = document.getElementById("syncLiveStatusBadge");
      const summaryText = document.getElementById("syncLiveSummaryText");
      const doneBtn = document.getElementById("syncLiveDoneBtn");
      if (statusBadge) {
        statusBadge.className = "badge badge-red";
        statusBadge.innerText = "Error";
      }
      if (summaryText) summaryText.innerText = `❌ Sync failed: ${err.message}`;
      if (doneBtn) doneBtn.style.display = "inline-flex";
    } finally {
      this.isSyncing = false;
      this.showProgress(false);
      window.onLogEntry = originalLogHook || (() => this.updateLogCounter());
    }
  }

  async forceRefreshSpecificPRs() {
    const input = document.getElementById("forceFetchInput");
    if (!input || !input.value.trim()) return;

    const raw = input.value.trim();
    const numbers = raw.split(/[,\s]+/).map(s => parseInt(s.replace("#", ""), 10)).filter(n => !isNaN(n));
    if (!numbers.length) return;

    if (this.isSyncing) return;
    this.isSyncing = true;
    const [owner, repo] = this.parseRepo(this.activeRepo);

    this.openSyncLiveModal();
    this.showProgress(true, `Force-refreshing ${numbers.length} PRs...`);

    const originalLogHook = window.onLogEntry;
    window.onLogEntry = (entry) => {
      this.updateLogCounter();
      if (entry) this.appendLiveSyncLog(entry);
    };

    try {
      await this.cache.forceRefreshPRs(owner, repo, numbers, (prog) => {
        const bar = document.getElementById("syncLiveProgressBar");
        const summaryText = document.getElementById("syncLiveSummaryText");
        if (prog.total && prog.completed) {
          const pct = Math.round((prog.completed / prog.total) * 100);
          this.updateProgress(pct, prog.message);
          if (bar) bar.style.width = `${pct}%`;
        } else {
          this.updateProgress(null, prog.message);
        }
        if (summaryText && prog.message) summaryText.innerText = prog.message;
      });

      input.value = "";
      this.loadFromCache();

      const statusBadge = document.getElementById("syncLiveStatusBadge");
      const summaryText = document.getElementById("syncLiveSummaryText");
      const doneBtn = document.getElementById("syncLiveDoneBtn");
      const bar = document.getElementById("syncLiveProgressBar");

      if (statusBadge) {
        statusBadge.className = "badge badge-green";
        statusBadge.innerText = "Complete";
      }
      if (bar) bar.style.width = "100%";
      if (summaryText) summaryText.innerText = `✅ Force-refreshed ${numbers.length} PRs.`;
      if (doneBtn) doneBtn.style.display = "inline-flex";

      this.showToast(`Updated ${numbers.length} PRs from GitHub!`);
    } catch (err) {
      alert(`Force refresh failed: ${err.message}`);
    } finally {
      this.isSyncing = false;
      this.showProgress(false);
      window.onLogEntry = originalLogHook || (() => this.updateLogCounter());
    }
  }

  async forceRefreshSinglePR(prNumber) {
    if (this.isSyncing) return;
    const [owner, repo] = this.parseRepo(this.activeRepo);
    this.showToast(`Refreshing PR #${prNumber} from GitHub...`);

    try {
      if (window.logger) {
        window.logger.info(`Force-refreshing single PR #${prNumber} from GitHub API...`, {
          component: "App",
          meta: { prNumber }
        });
      }
      await this.cache.forceRefreshPRs(owner, repo, [prNumber]);
      this.loadFromCache();
      this.showToast(`PR #${prNumber} refreshed!`);
    } catch (err) {
      if (window.logger) window.logger.error(`Failed to refresh PR #${prNumber}: ${err.message}`, { component: "App" });
      alert(`Refresh failed for PR #${prNumber}: ${err.message}`);
    }
  }

  showProgress(show, text = "") {
    const container = document.getElementById("progressBarContainer");
    const statusText = document.getElementById("syncStatusText");
    const bar = document.getElementById("progressBar");

    if (container) container.style.display = show ? "block" : "none";
    if (statusText) {
      statusText.style.display = show ? "block" : "none";
      statusText.innerText = text;
    }
    if (bar && !show) bar.style.width = "0%";
  }

  updateProgress(pct, text) {
    const bar = document.getElementById("progressBar");
    const statusText = document.getElementById("syncStatusText");
    if (bar && pct !== null) bar.style.width = `${pct}%`;
    if (statusText && text) statusText.innerText = text;
  }

  showToast(msg) {
    const toast = document.getElementById("toastMsg");
    if (!toast) return;
    toast.innerText = msg;
    toast.style.display = "block";
    setTimeout(() => {
      toast.style.display = "none";
    }, 3000);
  }

  /* --- KPI RENDERING --- */
  renderKPIs() {
    const total = this.prs.length;
    const ready = this.prs.filter(p => p.hub === "ready_to_merge").length;
    const close = this.prs.filter(p => p.hub === "duplicate_close").length;
    const core = this.prs.filter(p => p.hub === "requires_review").length;
    const rebase = this.prs.filter(p => p.hub === "needs_rebase").length;

    const setVal = (id, val) => {
      const el = document.getElementById(`kpi-val-${id}`);
      if (el) el.innerText = val;
    };

    setVal("total", total);
    setVal("ready", ready);
    setVal("close", close);
    setVal("core", core);
    setVal("rebase", rebase);
  }

  filterByHub(hub) {
    this.currentHubFilter = hub;
    document.querySelectorAll(".kpi-card").forEach(c => c.classList.remove("active"));
    const activeCard = document.getElementById(`kpi-${hub === 'all' ? 'total' : (hub === 'ready_to_merge' ? 'ready' : (hub === 'duplicate_close' ? 'close' : (hub === 'requires_review' ? 'core' : 'rebase')))}`);
    if (activeCard) activeCard.classList.add("active");
    this.renderAll();
  }

  toggleChip(el) {
    const filterType = el.dataset.filter;
    const val = el.dataset.val;

    if (filterType === "risk") {
      if (this.activeRiskFilters.has(val)) this.activeRiskFilters.delete(val);
      else this.activeRiskFilters.add(val);
    } else if (filterType === "author") {
      if (this.activeAuthorFilters.has(val)) this.activeAuthorFilters.delete(val);
      else this.activeAuthorFilters.add(val);
    }

    el.classList.toggle("active");
    this.renderAll();
  }

  setDateRange(val) {
    this.dateRangeFilter = val;
    this.renderAll();
  }

  resetFilters() {
    this.currentHubFilter = "all";
    this.activeRiskFilters.clear();
    this.activeAuthorFilters.clear();
    this.activeTagFilter = null;
    this.dateRangeFilter = "all";

    const search = document.getElementById("searchInput");
    if (search) search.value = "";
    const dateSel = document.getElementById("dateRangeSelect");
    if (dateSel) dateSel.value = "all";

    document.querySelectorAll(".chip").forEach(c => c.classList.remove("active"));
    document.querySelectorAll(".kpi-card").forEach(c => c.classList.remove("active"));
    const totalCard = document.getElementById("kpi-total");
    if (totalCard) totalCard.classList.add("active");

    this.renderAll();
  }

  setSortColumn(col) {
    if (this.sortColumn === col) {
      this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
    } else {
      this.sortColumn = col;
      this.sortDirection = col === "dates" || col === "number" || col === "blast" ? "desc" : "asc";
    }
    this.renderAll();
  }

  sortPRs(prs) {
    const factor = this.sortDirection === "asc" ? 1 : -1;
    return [...prs].sort((a, b) => {
      if (this.sortColumn === "number") {
        return (a.number - b.number) * factor;
      }
      if (this.sortColumn === "title") {
        return (a.title || "").localeCompare(b.title || "") * factor;
      }
      if (this.sortColumn === "dates") {
        const dateA = new Date(a.updated_at || a.created_at || 0).getTime();
        const dateB = new Date(b.updated_at || b.created_at || 0).getTime();
        return (dateA - dateB) * factor;
      }
      if (this.sortColumn === "risk") {
        return ((a.risk_weight || 1) - (b.risk_weight || 1)) * factor;
      }
      if (this.sortColumn === "blast") {
        const filesA = a.changed_files || (a.files ? a.files.length : 0);
        const filesB = b.changed_files || (b.files ? b.files.length : 0);
        return (filesA - filesB) * factor;
      }
      if (this.sortColumn === "subsystems") {
        const subA = (a.subsystems || []).join(", ");
        const subB = (b.subsystems || []).join(", ");
        return subA.localeCompare(subB) * factor;
      }
      return 0;
    });
  }

  filterPRs(prs) {
    const searchInput = document.getElementById("searchInput");
    const query = searchInput ? searchInput.value.toLowerCase().trim() : "";

    const now = new Date();

    return prs.filter(pr => {
      // 1. Search Query
      if (query) {
        const matchTitle = (pr.title || "").toLowerCase().includes(query);
        const matchNum = String(pr.number).includes(query);
        const matchBranch = (pr.branch || "").toLowerCase().includes(query);
        const matchAuthor = (pr.author && pr.author.login ? pr.author.login.toLowerCase() : "").includes(query);
        const matchFiles = (pr.files || []).some(f => (f.path || f.filename || "").toLowerCase().includes(query));
        if (!matchTitle && !matchNum && !matchBranch && !matchAuthor && !matchFiles) return false;
      }

      // 2. Risk Filter
      if (this.activeRiskFilters.size > 0) {
        if (!this.activeRiskFilters.has(pr.risk_level)) return false;
      }

      // 3. Author Filter
      if (this.activeAuthorFilters.size > 0) {
        const isBot = pr.author ? pr.author.is_bot : false;
        const login = pr.author ? pr.author.login.toLowerCase() : "";
        let matched = false;
        if (this.activeAuthorFilters.has("Human") && !isBot) matched = true;
        if (this.activeAuthorFilters.has("Jules") && login.includes("jules")) matched = true;
        if (this.activeAuthorFilters.has("Copilot") && (login.includes("copilot") || login.includes("github-actions"))) matched = true;
        if (!matched) return false;
      }

      // 4. Date Range Filter
      if (this.dateRangeFilter !== "all") {
        const updatedDate = new Date(pr.updated_at || pr.created_at);
        const diffMs = now - updatedDate;
        const diffDays = diffMs / (1000 * 60 * 60 * 24);
        if (this.dateRangeFilter === "today" && diffDays > 1) return false;
        if (this.dateRangeFilter === "7d" && diffDays > 7) return false;
        if (this.dateRangeFilter === "30d" && diffDays > 30) return false;
      }

      return true;
    });
  }

  toggleHubCollapse(hubId) {
    const isCurrentlyCollapsed = !!this.collapsedHubs[hubId];
    this.collapsedHubs[hubId] = !isCurrentlyCollapsed;
    localStorage.setItem(AppConfig.storageKeys.collapsedHubs, JSON.stringify(this.collapsedHubs));
    this.renderAll();
  }

  toggleAllHubs(expand) {
    const hubIds = ["ready_to_merge", "duplicate_close", "requires_review", "needs_rebase", "conflicts_core", "conflicts_ui", "conflicts_general"];
    hubIds.forEach(id => {
      this.collapsedHubs[id] = !expand;
    });
    localStorage.setItem(AppConfig.storageKeys.collapsedHubs, JSON.stringify(this.collapsedHubs));
    this.renderAll();
  }

  renderAll() {
    const container = document.getElementById("hubsContainer");
    if (!container) return;

    let html = "";
    const filteredPRs = this.filterPRs(this.prs);

    // Hub 1: Ready to Merge
    if (this.currentHubFilter === "all" || this.currentHubFilter === "ready_to_merge") {
      const hubPRs = this.sortPRs(filteredPRs.filter(p => p.hub === "ready_to_merge"));
      html += Templates.hubSection({
        hubId: "ready_to_merge",
        title: "🟢 Hub 1: Ready to Merge (Clean &amp; Verified)",
        badgeCount: hubPRs.length,
        badgeClass: "badge-green",
        isCollapsible: true,
        isCollapsed: !!this.collapsedHubs["ready_to_merge"],
        contentHtml: Templates.prTable({
          prs: hubPRs,
          activeRepo: this.activeRepo,
          sortColumn: this.sortColumn,
          sortDirection: this.sortDirection
        })
      });
    }

    // Hub 2: Close Duplicates
    if (this.currentHubFilter === "all" || this.currentHubFilter === "duplicate_close") {
      const hubPRs = filteredPRs.filter(p => p.hub === "duplicate_close");
      html += Templates.hubSection({
        hubId: "duplicate_close",
        title: "⚪ Hub 2: PRUNE &amp; CLOSE (Duplicate Bot Clusters)",
        badgeCount: hubPRs.length,
        badgeClass: "badge-purple",
        isCollapsible: true,
        isCollapsed: !!this.collapsedHubs["duplicate_close"],
        contentHtml: Templates.hub2DuplicateClusters({
          groups: this.duplicateGroups,
          activeRepo: this.activeRepo
        })
      });
    }

    // Hub 3: Architecture Review
    if (this.currentHubFilter === "all" || this.currentHubFilter === "requires_review") {
      const hubPRs = this.sortPRs(filteredPRs.filter(p => p.hub === "requires_review"));
      html += Templates.hubSection({
        hubId: "requires_review",
        title: "🟡 Hub 3: Requires Architecture Review (Core &amp; DB)",
        badgeCount: hubPRs.length,
        badgeClass: "badge-yellow",
        isCollapsible: true,
        isCollapsed: !!this.collapsedHubs["requires_review"],
        contentHtml: Templates.prTable({
          prs: hubPRs,
          activeRepo: this.activeRepo,
          sortColumn: this.sortColumn,
          sortDirection: this.sortDirection
        })
      });
    }

    // Hub 4: Merge Conflicts (Subdivided 4A, 4B, 4C)
    if (this.currentHubFilter === "all" || this.currentHubFilter === "needs_rebase") {
      const allConflictPRs = filteredPRs.filter(p => p.hub === "needs_rebase");
      const prs4A = this.sortPRs(allConflictPRs.filter(p => p.sub_hub === "conflicts_core"));
      const prs4B = this.sortPRs(allConflictPRs.filter(p => p.sub_hub === "conflicts_ui"));
      const prs4C = this.sortPRs(allConflictPRs.filter(p => p.sub_hub === "conflicts_general"));

      let conflictsHtml = `
        <div class="u-mt-8">
          ${Templates.hubSection({
            hubId: "conflicts_core",
            title: "🔴 Sub-Hub 4A: Database, Cron &amp; Core Generation Engine Conflicts",
            badgeCount: prs4A.length,
            badgeClass: "badge-critical",
            isCollapsible: true,
            isCollapsed: !!this.collapsedHubs["conflicts_core"],
            contentHtml: Templates.prTable({ prs: prs4A, isConflictHub: true, activeRepo: this.activeRepo, sortColumn: this.sortColumn, sortDirection: this.sortDirection })
          })}

          ${Templates.hubSection({
            hubId: "conflicts_ui",
            title: "🟠 Sub-Hub 4B: Admin UI, Planner, Repositories &amp; AJAX Conflicts",
            badgeCount: prs4B.length,
            badgeClass: "badge-yellow",
            isCollapsible: true,
            isCollapsed: !!this.collapsedHubs["conflicts_ui"],
            contentHtml: Templates.prTable({ prs: prs4B, isConflictHub: true, activeRepo: this.activeRepo, sortColumn: this.sortColumn, sortDirection: this.sortDirection })
          })}

          ${Templates.hubSection({
            hubId: "conflicts_general",
            title: "🟡 Sub-Hub 4C: Localization, Documentation &amp; Testing Conflicts",
            badgeCount: prs4C.length,
            badgeClass: "badge-gray",
            isCollapsible: true,
            isCollapsed: !!this.collapsedHubs["conflicts_general"],
            contentHtml: Templates.prTable({ prs: prs4C, isConflictHub: true, activeRepo: this.activeRepo, sortColumn: this.sortColumn, sortDirection: this.sortDirection })
          })}
        </div>
      `;

      html += Templates.hubSection({
        hubId: "needs_rebase",
        title: "🔴 Hub 4: Merge Conflicts (Needs Rebase &amp; Conflict Fix)",
        badgeCount: allConflictPRs.length,
        badgeClass: "badge-red",
        isCollapsible: true,
        isCollapsed: !!this.collapsedHubs["needs_rebase"],
        contentHtml: conflictsHtml
      });
    }

    container.innerHTML = html;
  }

  /* --- MODAL DIALOGS --- */
  openModal(prNumber) {
    const pr = this.prs.find(p => p.number === prNumber);
    if (!pr) return;

    document.getElementById("modalTitle").innerText = `PR #${pr.number}: ${pr.title}`;
    document.getElementById("modalBody").innerHTML = Templates.infoModalContent(pr);
    document.getElementById("infoModal").classList.add("open");
  }

  closeModal(e) {
    if (e.target.id === "infoModal") {
      document.getElementById("infoModal").classList.remove("open");
    }
  }

  closeModalDirect() {
    document.getElementById("infoModal").classList.remove("open");
  }

  openMergeModal(prNumber) {
    const pr = this.prs.find(p => p.number === prNumber);
    if (!pr) return;
    this.pendingActionPR = pr;

    document.getElementById("mergeModalPRTitle").innerText = `Merge PR #${pr.number}: ${pr.title}`;
    document.getElementById("mergeCommitTitle").value = `${pr.title} (#${pr.number})`;
    document.getElementById("mergeCommitMsg").value = `Squash merged via PR Triage Command Center.`;
    document.getElementById("mergeModal").classList.add("open");
  }

  closeMergeModal() {
    document.getElementById("mergeModal").classList.remove("open");
  }

  async executeMerge() {
    if (!this.pendingActionPR) return;
    const pr = this.pendingActionPR;
    const [owner, repo] = this.parseRepo(this.activeRepo);
    const method = document.getElementById("mergeMethodSelect").value;
    const title = document.getElementById("mergeCommitTitle").value.trim();
    const msg = document.getElementById("mergeCommitMsg").value.trim();

    try {
      this.closeMergeModal();
      this.showToast(`Merging PR #${pr.number}...`);
      await this.api.mergePR(owner, repo, pr.number, method, title, msg);
      this.showToast(`PR #${pr.number} merged successfully!`);
      await this.cache.forceRefreshPRs(owner, repo, [pr.number]);
      this.loadFromCache();
    } catch (err) {
      alert(`Merge failed: ${err.message}`);
    }
  }

  openCloseModal(prNumber, defaultComment = "") {
    const pr = this.prs.find(p => p.number === prNumber);
    if (!pr) return;
    this.pendingActionPR = pr;

    document.getElementById("closeModalPRTitle").innerText = `Close PR #${pr.number}: ${pr.title}`;
    document.getElementById("closeCommentInput").value = defaultComment || "Closing as superseded / obsolete via PR Triage Command Center.";
    document.getElementById("closeModal").classList.add("open");
  }

  closeCloseModal() {
    document.getElementById("closeModal").classList.remove("open");
  }

  async executeClose() {
    if (!this.pendingActionPR) return;
    const pr = this.pendingActionPR;
    const [owner, repo] = this.parseRepo(this.activeRepo);
    const comment = document.getElementById("closeCommentInput").value.trim();

    try {
      this.closeCloseModal();
      this.showToast(`Closing PR #${pr.number}...`);
      await this.api.closePR(owner, repo, pr.number, comment);
      this.showToast(`PR #${pr.number} closed!`);
      await this.cache.forceRefreshPRs(owner, repo, [pr.number]);
      this.loadFromCache();
    } catch (err) {
      alert(`Close failed: ${err.message}`);
    }
  }

  openLogModal() {
    this.renderLogsInModal();
    document.getElementById("logModal").classList.add("open");
  }

  closeLogModal() {
    document.getElementById("logModal").classList.remove("open");
  }

  renderLogsInModal() {
    const container = document.getElementById("logModalContent");
    const filter = document.getElementById("logLevelFilter").value;
    const search = document.getElementById("logSearchInput").value.toLowerCase().trim();

    if (!window.logger) return;
    const logs = window.logger.getLogs().filter(e => {
      if (filter === "API_ONLY" && !e.isApiCall) return false;
      if (filter === "ERRORS_ONLY" && e.level !== "ERROR" && e.level !== "WARN") return false;
      if (search && !e.message.toLowerCase().includes(search) && !e.component.toLowerCase().includes(search)) return false;
      return true;
    });

    container.innerHTML = logs.map(e => Templates.logRow(e)).join("") || '<div class="empty-state">No logs match criteria.</div>';
  }

  openTriageWizard() {
    if (!this.wizard) {
      this.wizard = new TriageWizard(this);
      window.wizard = this.wizard;
    }
    this.wizard.open();
  }

  openSettingsModal(tab = "general") {
    const tokenInput = document.getElementById("patInput");
    const repoInput = document.getElementById("repoInput");
    const syncModeSelect = document.getElementById("syncModeSelect");
    const execModeSelect = document.getElementById("execModeSelect");

    if (tokenInput) tokenInput.value = this.api.getToken();
    if (repoInput) repoInput.value = this.activeRepo;
    if (syncModeSelect) syncModeSelect.value = localStorage.getItem(AppConfig.storageKeys.syncMode) || AppConfig.syncMode;
    if (execModeSelect) execModeSelect.value = AppConfig.getExecMode();

    this.switchSettingsTab(tab);
    this.renderRulesEditor();

    document.getElementById("settingsModal").classList.add("open");
  }

  closeSettingsModal() {
    document.getElementById("settingsModal").classList.remove("open");
  }

  switchSettingsTab(tab) {
    this.activeSettingsTab = tab;
    document.querySelectorAll(".settings-tab-btn").forEach(b => {
      b.classList.toggle("active", b.dataset.tab === tab);
    });
    const generalPane = document.getElementById("settingsPaneGeneral");
    const rulesPane = document.getElementById("settingsPaneRules");
    if (generalPane) generalPane.style.display = tab === "general" ? "block" : "none";
    if (rulesPane) rulesPane.style.display = tab === "rules" ? "block" : "none";
  }

  renderRulesEditor() {
    const container = document.getElementById("subsystemsListEditor");
    if (!container) return;

    const subsystems = AppConfig.subsystemRules || [];
    const riskRules = AppConfig.riskRules || {};

    container.innerHTML = subsystems.map((sub, idx) => Templates.subsystemRuleCard({
      sub,
      idx,
      isFirst: idx === 0,
      isLast: idx === subsystems.length - 1
    })).join("");

    const largeFileThresholdInput = document.getElementById("riskLargeFileThreshold");
    const coreDbCheck = document.getElementById("riskCoreDbCheck");
    const lineThresholdInput = document.getElementById("riskLineThreshold");
    if (largeFileThresholdInput) largeFileThresholdInput.value = riskRules.largeFileThreshold || 15;
    if (coreDbCheck) coreDbCheck.checked = !!riskRules.maxCriticalRiskWhenCoreAndDB;
    if (lineThresholdInput) lineThresholdInput.value = riskRules.highRiskLinesChangedThreshold || 500;

    this.syncRulesToRawJSON();
  }

  addSubsystem() {
    AppConfig.subsystemRules.push({
      name: "New Subsystem",
      keywords: ["path/to/feature", "feature-"],
      isCore: false
    });
    this.renderRulesEditor();
  }

  deleteSubsystem(idx) {
    if (confirm(`Delete subsystem '${AppConfig.subsystemRules[idx].name}'?`)) {
      AppConfig.subsystemRules.splice(idx, 1);
      this.renderRulesEditor();
    }
  }

  moveSubsystem(idx, delta) {
    const newIdx = idx + delta;
    if (newIdx < 0 || newIdx >= AppConfig.subsystemRules.length) return;
    const item = AppConfig.subsystemRules.splice(idx, 1)[0];
    AppConfig.subsystemRules.splice(newIdx, 0, item);
    this.renderRulesEditor();
  }

  collectVisualRules() {
    const cards = document.querySelectorAll("#subsystemsListEditor .rule-edit-card");
    const subsystems = [];
    cards.forEach(card => {
      const name = card.querySelector(".rule-name-input").value.trim();
      const isCore = card.querySelector(".rule-core-check").checked;
      const rawKeywords = card.querySelector(".rule-keywords-input").value;
      const keywords = rawKeywords.split(",").map(k => k.trim()).filter(Boolean);
      if (name) {
        subsystems.push({ name, keywords, isCore });
      }
    });

    const largeFileThreshold = parseInt(document.getElementById("riskLargeFileThreshold").value, 10) || 15;
    const maxCriticalRiskWhenCoreAndDB = document.getElementById("riskCoreDbCheck").checked;
    const highRiskLinesChangedThreshold = parseInt(document.getElementById("riskLineThreshold").value, 10) || 500;

    return {
      subsystems,
      riskRules: {
        largeFileThreshold,
        maxCriticalRiskWhenCoreAndDB,
        highRiskLinesChangedThreshold
      }
    };
  }

  syncRulesToRawJSON() {
    const { subsystems, riskRules } = this.collectVisualRules();
    const jsonArea = document.getElementById("rawRulesJSON");
    if (jsonArea) {
      jsonArea.value = JSON.stringify({ subsystems, riskRules }, null, 2);
    }
  }

  syncRawJSONToVisual() {
    const jsonArea = document.getElementById("rawRulesJSON");
    if (!jsonArea) return;
    try {
      const parsed = JSON.parse(jsonArea.value);
      if (parsed.subsystems && Array.isArray(parsed.subsystems)) {
        AppConfig.subsystemRules = parsed.subsystems;
      }
      if (parsed.riskRules && typeof parsed.riskRules === "object") {
        AppConfig.riskRules = parsed.riskRules;
      }
      this.renderRulesEditor();
      this.showToast("Rules updated from JSON!");
    } catch (e) {
      alert(`Invalid JSON format: ${e.message}`);
    }
  }

  formatRawJSON() {
    const jsonArea = document.getElementById("rawRulesJSON");
    if (!jsonArea) return;
    try {
      const parsed = JSON.parse(jsonArea.value);
      jsonArea.value = JSON.stringify(parsed, null, 2);
    } catch (e) {
      alert(`Invalid JSON: ${e.message}`);
    }
  }

  resetRulesToDefault() {
    if (confirm("Reset all subsystem keywords and risk weights to factory defaults?")) {
      AppConfig.resetCustomRules();
      this.renderRulesEditor();
      this.reclassifyAndRender();
      this.showToast("Reset to factory default rules!");
    }
  }

  exportRulesJSON() {
    const { subsystems, riskRules } = this.collectVisualRules();
    const data = JSON.stringify({ subsystems, riskRules, exportedAt: new Date().toISOString() }, null, 2);
    const blob = new Blob([data], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `pr_triage_rules_${Date.now()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    this.showToast("Rules exported!");
  }

  importRulesJSON(inputEl) {
    const file = inputEl.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const parsed = JSON.parse(e.target.result);
        if (parsed.subsystems) AppConfig.subsystemRules = parsed.subsystems;
        if (parsed.riskRules) AppConfig.riskRules = parsed.riskRules;
        this.renderRulesEditor();
        this.showToast("Rules imported successfully!");
      } catch (err) {
        alert(`Error importing rules: ${err.message}`);
      }
    };
    reader.readAsText(file);
    inputEl.value = "";
  }

  reclassifyAndRender() {
    this.loadFromCache();
    if (this.wizard) this.wizard.initStepData();
  }

  async saveSettings() {
    const tokenInput = document.getElementById("patInput");
    const repoInput = document.getElementById("repoInput");
    const syncModeSelect = document.getElementById("syncModeSelect");
    const execModeSelect = document.getElementById("execModeSelect");

    const token = tokenInput ? tokenInput.value.trim() : "";
    const repo = repoInput ? repoInput.value.trim() : AppConfig.defaultRepo;
    const syncMode = syncModeSelect ? syncModeSelect.value : "graphql";
    const execMode = execModeSelect ? execModeSelect.value : "api";

    const repoChanged = (this.activeRepo !== repo);

    this.api.setToken(token);
    this.activeRepo = repo;
    AppConfig.setExecMode(execMode);
    localStorage.setItem(AppConfig.storageKeys.activeRepo, repo);
    localStorage.setItem(AppConfig.storageKeys.syncMode, syncMode);

    const { subsystems, riskRules } = this.collectVisualRules();
    AppConfig.saveCustomRules(subsystems, riskRules);

    document.getElementById("activeRepoLabel").innerText = repo;
    this.closeSettingsModal();

    this.reclassifyAndRender();
    this.showToast("Settings & Rules saved! PRs reclassified.");

    if (token && repoChanged) {
      this.populateUserRepos();
      this.api.getRateLimit();
      this.syncAll();
    }
  }

  clearActiveRepoCache() {
    const [owner, repo] = this.parseRepo(this.activeRepo);
    if (confirm(`Clear all cached PR data for ${owner}/${repo}?`)) {
      this.cache.clearCache(owner, repo);
      this.loadFromCache();
      this.showToast("Cache cleared.");
    }
  }

  async populateUserRepos() {
    try {
      const repos = await this.api.getUserRepos();
      const select = document.getElementById("userReposSelect");
      if (select && repos.length) {
        select.innerHTML = '<option value="">-- Choose from your GitHub Repos --</option>' + 
          repos.map(r => `<option value="${r.full_name}">${r.full_name}</option>`).join("");
        select.style.display = "block";
      }
    } catch (e) {
      console.warn("Could not fetch user repos:", e);
    }
  }

  selectUserRepo(val) {
    if (val) {
      document.getElementById("repoInput").value = val;
    }
  }

  /* --- EXPORT FUNCTIONALITY --- */
  getFilteredPRs() {
    let filtered = this.prs;
    if (this.currentHubFilter !== "all") {
      filtered = filtered.filter(p => p.hub === this.currentHubFilter);
    }
    return this.filterPRs(filtered);
  }

  openExportModal() {
    const allCountEl = document.getElementById("exportScopeAllCount");
    const filteredCountEl = document.getElementById("exportScopeFilteredCount");
    const fileNameEl = document.getElementById("exportFileNamePreview");

    const [owner, repo] = this.parseRepo(this.activeRepo);
    const dateStamp = new Date().toISOString().slice(0, 10);
    const defaultFileName = `${repo}_prs_export_${dateStamp}.json`;

    if (allCountEl) allCountEl.innerText = this.prs.length;
    if (filteredCountEl) filteredCountEl.innerText = this.getFilteredPRs().length;
    if (fileNameEl) fileNameEl.innerText = defaultFileName;

    this.updateExportPreview();
    const modal = document.getElementById("exportModal");
    if (modal) modal.classList.add("open");
  }

  closeExportModal() {
    const modal = document.getElementById("exportModal");
    if (modal) modal.classList.remove("open");
  }

  applyExportPreset(preset) {
    const bodySelect = document.getElementById("exp_prop_body_mode");
    const setCheck = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.checked = val;
    };

    if (preset === "agent") {
      if (bodySelect) bodySelect.value = "partial_300";
      setCheck("exp_prop_status", true);
      setCheck("exp_prop_created", true);
      setCheck("exp_prop_updated", true);
      setCheck("exp_prop_author", true);
      setCheck("exp_prop_labels", true);
      setCheck("exp_prop_branch", true);
      setCheck("exp_prop_subsystems", true);
      setCheck("exp_prop_risk", true);
      setCheck("exp_prop_hub", true);
      setCheck("exp_prop_diff_stats", false);
      setCheck("exp_prop_url", true);
    } else if (preset === "minimal") {
      if (bodySelect) bodySelect.value = "none";
      setCheck("exp_prop_status", true);
      setCheck("exp_prop_created", false);
      setCheck("exp_prop_updated", false);
      setCheck("exp_prop_author", true);
      setCheck("exp_prop_labels", false);
      setCheck("exp_prop_branch", false);
      setCheck("exp_prop_subsystems", true);
      setCheck("exp_prop_risk", false);
      setCheck("exp_prop_hub", true);
      setCheck("exp_prop_diff_stats", false);
      setCheck("exp_prop_url", false);
    } else if (preset === "full") {
      if (bodySelect) bodySelect.value = "full";
      setCheck("exp_prop_status", true);
      setCheck("exp_prop_created", true);
      setCheck("exp_prop_updated", true);
      setCheck("exp_prop_author", true);
      setCheck("exp_prop_labels", true);
      setCheck("exp_prop_branch", true);
      setCheck("exp_prop_subsystems", true);
      setCheck("exp_prop_risk", true);
      setCheck("exp_prop_hub", true);
      setCheck("exp_prop_diff_stats", true);
      setCheck("exp_prop_url", true);
    }

    this.updateExportPreview();
  }

  getExportOptions() {
    const isFiltered = document.querySelector('input[name="exportScope"]:checked')?.value === "filtered";
    const format = document.querySelector('input[name="exportFormat"]:checked')?.value || "pretty";
    const bodyMode = document.getElementById("exp_prop_body_mode")?.value || "partial_300";

    const getVal = (id) => !!document.getElementById(id)?.checked;

    return {
      scope: isFiltered ? "filtered" : "all",
      format,
      bodyMode,
      status: getVal("exp_prop_status"),
      created: getVal("exp_prop_created"),
      updated: getVal("exp_prop_updated"),
      author: getVal("exp_prop_author"),
      labels: getVal("exp_prop_labels"),
      branch: getVal("exp_prop_branch"),
      subsystems: getVal("exp_prop_subsystems"),
      risk: getVal("exp_prop_risk"),
      hub: getVal("exp_prop_hub"),
      diff_stats: getVal("exp_prop_diff_stats"),
      url: getVal("exp_prop_url")
    };
  }

  serializePRForExport(pr, options) {
    const item = {
      id: pr.number,
      title: pr.title || ""
    };

    if (options.bodyMode === "full") {
      item.body = pr.body || "";
    } else if (options.bodyMode === "partial_300") {
      const b = (pr.body || "").trim();
      item.body = b.length > 300 ? b.slice(0, 300) + "..." : b;
    } else if (options.bodyMode === "partial_100") {
      const b = (pr.body || "").trim();
      item.body = b.length > 100 ? b.slice(0, 100) + "..." : b;
    }

    if (options.status) {
      item.status = pr.mergeable || "UNKNOWN";
      item.merge_state = pr.merge_state || "unknown";
      item.is_draft = !!pr.is_draft;
    }

    if (options.created) item.created_at = pr.created_at || "";
    if (options.updated) item.updated_at = pr.updated_at || "";

    if (options.author) {
      const authorLogin = typeof pr.author === "string" ? pr.author : (pr.author?.login || "unknown");
      item.author = authorLogin;
      item.bot_type = pr.bot_type || authorLogin;
    }

    if (options.labels) item.labels = pr.labels || [];
    if (options.branch) {
      item.branch = pr.branch || "";
      item.base_branch = pr.base_branch || "main";
    }

    if (options.subsystems) item.subsystems = Array.from(pr.subsystems || []);

    if (options.risk) {
      item.risk_level = pr.risk_level || "MEDIUM";
      item.risk_reason = pr.risk_reason || "";
      item.risk_weight = pr.risk_weight || 2;
    }

    if (options.hub) {
      item.hub = pr.hub || pr.assigned_hub || "general";
      if (pr.notes && pr.notes.length) item.notes = pr.notes;
      if (pr.duplicate_group_id) {
        item.cluster_id = pr.duplicate_group_id;
        item.is_cluster_winner = !!pr.is_winner;
      }
    }

    if (options.diff_stats) {
      item.changed_files = pr.changed_files !== undefined ? pr.changed_files : (pr.files ? pr.files.length : 0);
      item.additions = pr.additions || 0;
      item.deletions = pr.deletions || 0;
    }

    if (options.url) {
      item.url = Templates.getPrUrl(pr, this.activeRepo);
    }

    return item;
  }

  buildExportPayload(options = null) {
    const opts = options || this.getExportOptions();
    const sourcePRs = opts.scope === "filtered" ? this.getFilteredPRs() : this.prs;

    const serialized = sourcePRs.map(pr => this.serializePRForExport(pr, opts));

    return {
      repository: this.activeRepo,
      exported_at: new Date().toISOString(),
      total_prs: serialized.length,
      scope: opts.scope,
      prs: serialized
    };
  }

  updateExportPreview() {
    const opts = this.getExportOptions();
    const sourcePRs = opts.scope === "filtered" ? this.getFilteredPRs() : this.prs;
    const samplePR = sourcePRs[0] || (this.prs[0] || {
      number: 2062,
      title: "NunezScheduler: Optimized Research Planner Flow",
      body: "This PR optimizes the research planner workflow and reduces memory allocations.",
      branch: "fix/planner-search",
      author: "rpnunez",
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      mergeable: "MERGEABLE",
      subsystems: ["Admin UI & Planner", "Documentation"],
      risk_level: "LOW"
    });

    const sampleItem = this.serializePRForExport(samplePR, opts);
    const samplePayload = {
      repository: this.activeRepo,
      exported_at: new Date().toISOString(),
      total_prs: sourcePRs.length,
      scope: opts.scope,
      prs: [sampleItem]
    };

    const codeEl = document.getElementById("exportPreviewCode");
    const sizeEl = document.getElementById("exportPayloadSizeEstimate");

    const jsonStr = JSON.stringify(samplePayload, null, opts.format === "pretty" ? 2 : 0);
    if (codeEl) codeEl.innerText = jsonStr;

    // Estimate full size
    const avgItemLen = JSON.stringify(sampleItem).length;
    const estTotalBytes = (avgItemLen * sourcePRs.length) + 150;
    const estKB = Math.round(estTotalBytes / 1024 * 10) / 10;
    if (sizeEl) sizeEl.innerText = `Total: ${sourcePRs.length} PRs (~${estKB} KB)`;
  }

  downloadExportJSON() {
    const opts = this.getExportOptions();
    const payload = this.buildExportPayload(opts);
    const jsonStr = JSON.stringify(payload, null, opts.format === "pretty" ? 2 : 0);

    const [owner, repo] = this.parseRepo(this.activeRepo);
    const dateStamp = new Date().toISOString().slice(0, 10);
    const fileName = `${repo}_prs_export_${dateStamp}.json`;

    const blob = new Blob([jsonStr], { type: "application/json;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    if (window.logger) {
      window.logger.info(`Exported ${payload.total_prs} PRs to ${fileName} (${opts.scope} scope, ${opts.format} format)`, {
        component: "Export",
        meta: { totalPrs: payload.total_prs, fileName, options: opts }
      });
    }

    this.showToast(`✅ Exported ${payload.total_prs} PRs to ${fileName}`);
    this.closeExportModal();
  }

  async copyExportJSON() {
    const opts = this.getExportOptions();
    const payload = this.buildExportPayload(opts);
    const jsonStr = JSON.stringify(payload, null, opts.format === "pretty" ? 2 : 0);

    try {
      await navigator.clipboard.writeText(jsonStr);
      if (window.logger) {
        window.logger.info(`Copied ${payload.total_prs} PRs JSON to clipboard`, { component: "Export" });
      }
      this.showToast(`📋 Copied ${payload.total_prs} PRs JSON to clipboard!`);
    } catch (err) {
      alert(`Could not copy to clipboard: ${err.message}`);
    }
  }
}

window.App = App;

window.addEventListener("DOMContentLoaded", () => {
  window.app = new App();
  window.wizard = new TriageWizard(window.app);
  window.app.wizard = window.wizard;
});
