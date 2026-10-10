/**
 * PR Triage Command Center - Pure Component Templates Registry
 * Centralized ES6 string-interpolated template components.
 * ZERO inline styles — all styling is driven by semantic CSS classes.
 */
const Templates = {
  escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  getPrUrl(pr, activeRepo = "rpnunez/wp-ai-scheduler") {
    if (pr.url && pr.url.startsWith("http")) return pr.url;
    if (pr.html_url && pr.html_url.startsWith("http")) return pr.html_url;
    return `https://github.com/${activeRepo}/pull/${pr.number}`;
  },

  getAuthorInfo(author) {
    if (!author) return { login: "unknown", is_bot: false, role: "Unknown", label: "Unknown" };
    const login = typeof author === "string" ? author : (author.login || "unknown");
    const isBot = (typeof author === "object" && author.is_bot) || 
                  login.toLowerCase().includes("bot") || 
                  login.toLowerCase().includes("jules") || 
                  login.toLowerCase().includes("copilot");
    const role = isBot ? "Bot" : "Human";
    return { login, is_bot: isBot, role, label: `${role} (${login})` };
  },

  /* --- KPI CARDS --- */
  kpiCard({ id, filterVal, label, value, desc, labelClass = "", valClass = "", isActive = false }) {
    return `
      <div class="kpi-card ${isActive ? 'active' : ''}" onclick="window.app.filterByHub('${filterVal}')" id="kpi-${id}">
        <span class="kpi-label ${labelClass}">${label}</span>
        <span class="kpi-value ${valClass}" id="kpi-val-${id}">${value}</span>
        <span class="kpi-desc">${desc}</span>
      </div>
    `;
  },

  /* --- BADGES & CHIPS --- */
  authorBadge(author) {
    const info = this.getAuthorInfo(author);
    const badgeClass = info.is_bot ? "badge-purple" : "badge-blue";
    return `<span class="badge ${badgeClass}">${this.escapeHtml(info.login)}</span>`;
  },

  subsystemBadges(subsystems) {
    if (!subsystems || subsystems.length === 0) return "";
    return subsystems.map(s => `<span class="tag-badge">${this.escapeHtml(s)}</span>`).join(" ");
  },

  combinedRiskChip(pr) {
    const risk = pr.risk_level || "MEDIUM";
    
    // Determine merge status cleanly
    let status = "MERGEABLE";
    let statusChipClass = "status-chip-clean";
    let statusIcon = "•";

    const mergeable = (pr.mergeable || "").toUpperCase();
    const mergeState = (pr.merge_state || "").toUpperCase();

    if (mergeable === "CONFLICTING" || mergeState === "DIRTY" || mergeState === "CONFLICTING") {
      status = "CONFLICT";
      statusChipClass = "status-chip-conflict";
      statusIcon = "⚠️";
    } else if (mergeState === "BEHIND") {
      status = "BEHIND";
      statusChipClass = "status-chip-unknown";
      statusIcon = "•";
    } else if (mergeState === "BLOCKED") {
      status = "BLOCKED";
      statusChipClass = "status-chip-unknown";
      statusIcon = "•";
    } else if (mergeable === "MERGEABLE" || mergeState === "CLEAN") {
      status = "MERGEABLE";
      statusChipClass = "status-chip-clean";
      statusIcon = "•";
    } else {
      // If GitHub is computing status in background, inspect notes or default to MERGEABLE
      if (pr.notes && pr.notes.some(n => n.toLowerCase().includes("conflict"))) {
        status = "CONFLICT";
        statusChipClass = "status-chip-conflict";
        statusIcon = "⚠️";
      } else {
        status = "MERGEABLE";
        statusChipClass = "status-chip-clean";
        statusIcon = "•";
      }
    }

    let riskBadgeClass = "badge-gray";
    if (risk === "CRITICAL") riskBadgeClass = "badge-critical";
    else if (risk === "HIGH") riskBadgeClass = "badge-red";
    else if (risk === "MEDIUM") riskBadgeClass = "badge-yellow";
    else if (risk === "LOW") riskBadgeClass = "badge-green";

    return `
      <div class="risk-status-cluster" title="Risk: ${risk} (${this.escapeHtml(pr.risk_reason || '')}) | Merge Status: ${status}">
        <span class="risk-pill ${riskBadgeClass}">${risk}</span>
        <span class="status-chip ${statusChipClass}">${statusIcon} ${status}</span>
      </div>
    `;
  },

  datesCell(pr) {
    const updatedStr = pr.updated_at ? new Date(pr.updated_at).toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : "N/A";
    const openedStr = pr.created_at ? new Date(pr.created_at).toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : "N/A";

    return `
      <div class="dates-cell">
        <div class="dates-updated">Updated: ${updatedStr}</div>
        <div class="dates-opened">Opened: ${openedStr}</div>
      </div>
    `;
  },

  blastRadiusCell(pr) {
    const filesCount = pr.changed_files || (pr.files ? pr.files.length : 0);
    const adds = pr.additions || 0;
    const dels = pr.deletions || 0;
    return `
      <div class="blast-cell">
        <div class="blast-files">📁 ${filesCount} files</div>
        <div class="diff-stat">
          <span class="diff-add">+${adds}</span> / <span class="diff-del">-${dels}</span>
        </div>
      </div>
    `;
  },

  /* --- TABLE & ROWS --- */
  prTable({ prs, isDuplicateHub = false, isConflictHub = false, activeRepo, sortColumn = "dates", sortDirection = "desc" }) {
    if (!prs || prs.length === 0) {
      return `<div class="empty-state">No pull requests match the selected filters.</div>`;
    }

    const renderTh = (col, label, thClass) => {
      const isSorted = sortColumn === col;
      const icon = isSorted ? (sortDirection === 'asc' ? '▲' : '▼') : '▴▾';
      return `
        <th class="${thClass}" onclick="window.app.setSortColumn('${col}')">
          <div class="th-content">
            <span>${label}</span>
            <span class="sort-icon ${isSorted ? 'active' : ''}">${icon}</span>
          </div>
        </th>
      `;
    };

    return `
      <div class="table-container">
        <table class="triage-table">
          <thead>
            <tr>
              ${renderTh('number', 'PR #', 'col-pr-num')}
              ${renderTh('title', 'Title &amp; Branch', 'col-title')}
              ${renderTh('dates', 'Dates', 'col-dates')}
              ${renderTh('subsystems', 'Subsystems', 'col-subsystems')}
              ${renderTh('risk', 'Risk &amp; Status', 'col-risk')}
              ${renderTh('blast', 'Blast Radius', 'col-blast')}
              <th class="col-actions"><div class="th-content"><span>Quick Actions</span></div></th>
            </tr>
          </thead>
          <tbody>
            ${prs.map(pr => this.prRow({ pr, activeRepo, isDuplicateHub, isConflictHub })).join("")}
          </tbody>
        </table>
      </div>
    `;
  },

  prRow({ pr, activeRepo, isDuplicateHub = false, isConflictHub = false }) {
    const authorInfo = this.getAuthorInfo(pr.author);
    const prUrl = this.getPrUrl(pr, activeRepo);

    let actionButton = "";
    if (isDuplicateHub || isConflictHub) {
      actionButton = `
        <button class="btn btn-sm btn-danger action-btn" title="Close Pull Request" onclick="window.app.openCloseModal(${pr.number})">
          ✕ Close
        </button>
      `;
    } else {
      actionButton = `
        <button class="btn btn-sm btn-primary action-btn" title="Merge Pull Request" onclick="window.app.openMergeModal(${pr.number})">
          ⚡ Merge
        </button>
      `;
    }

    return `
      <tr id="pr-row-${pr.number}">
        <td class="col-pr-num">
          <a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a>
        </td>
        <td class="col-title clickable-cell" onclick="window.app.openModal(${pr.number})" title="Click to view details for PR #${pr.number}">
          <a href="${prUrl}" target="_blank" class="pr-title-link" onclick="event.stopPropagation()">${this.escapeHtml(pr.title)}</a>
          <div class="pr-meta-line">
            <span class="pr-branch-name">${this.escapeHtml(pr.branch)}</span>
            <span class="meta-dot">&bull;</span>
            <span class="pr-author-name">Author: ${this.escapeHtml(authorInfo.label)}</span>
          </div>
          ${(pr.notes && pr.notes.length) ? `
            <div class="pr-notes-box">
              ${pr.notes.map(n => `<span class="pr-note-tag">⚠️ ${this.escapeHtml(n)}</span>`).join("")}
            </div>
          ` : ''}
        </td>
        <td class="col-dates clickable-cell" onclick="window.app.openModal(${pr.number})" title="Click to view details for PR #${pr.number}">
          ${this.datesCell(pr)}
        </td>
        <td class="col-subsystems">
          <div class="subsystems-wrap">
            ${this.subsystemBadges(pr.subsystems)}
          </div>
        </td>
        <td class="col-risk">
          ${this.combinedRiskChip(pr)}
        </td>
        <td class="col-blast">
          ${this.blastRadiusCell(pr)}
        </td>
        <td class="col-actions">
          <div class="actions-cluster">
            ${actionButton}
            <button class="btn btn-sm action-btn btn-icon" title="Force Refresh PR #${pr.number} from GitHub" onclick="window.app.forceRefreshSinglePR(${pr.number})">
              🔄
            </button>
            <button class="btn btn-sm action-btn" title="View Detailed Files &amp; Diff Info" onclick="window.app.openModal(${pr.number})">
              ℹ️ More
            </button>
          </div>
        </td>
      </tr>
    `;
  },

  /* --- HUB SECTIONS --- */
  hubSection({ hubId, title, badgeCount, badgeClass = "badge-blue", isCollapsible = true, isCollapsed = false, contentHtml }) {
    return `
      <section class="hub-section ${isCollapsed ? 'collapsed' : ''}" id="hub-section-${hubId}">
        <div class="hub-header" onclick="${isCollapsible ? `window.app.toggleHubCollapse('${hubId}')` : ''}">
          <div class="hub-header-left">
            <h2 class="hub-title">${title}</h2>
            <span class="badge ${badgeClass}">${badgeCount}</span>
          </div>
          ${isCollapsible ? `
            <button class="hub-collapse-btn" title="Toggle Hub Collapse">
              <span class="collapse-icon">${isCollapsed ? '▼ Expand' : '▲ Collapse'}</span>
            </button>
          ` : ''}
        </div>
        <div class="hub-content" id="hub-content-${hubId}">
          ${contentHtml}
        </div>
      </section>
    `;
  },

  /* --- DUPLICATE CLUSTERS (HUB 2 ON MAIN DASHBOARD & STEP 1 IN WIZARD) --- */
  hub2DuplicateClusters({ groups, activeRepo }) {
    if (!groups || groups.length === 0) {
      return `<div class="empty-state">🎉 No duplicate PR clusters detected. All open PRs appear unique!</div>`;
    }

    return `
      <div class="wizard-clusters-list u-mt-8">
        ${groups.map(group => {
          const keeper = group.winner;
          const allGroupPRs = [keeper, ...(group.toClose || [])].filter(Boolean);
          const keeperNum = keeper ? keeper.number : (allGroupPRs[0] ? allGroupPRs[0].number : 0);

          return `
            <div class="wizard-cluster-card" id="hub-cluster-${group.groupId}">
              <div class="wizard-cluster-card-header">
                <div class="cluster-card-title-wrap">
                  <strong class="cluster-card-title">${this.escapeHtml(group.title)}</strong>
                  <div class="cluster-card-desc">${this.escapeHtml(group.description)}</div>
                </div>
                <span class="badge badge-purple">${allGroupPRs.length} PRs in cluster</span>
              </div>

              <div class="wizard-cluster-table-wrap">
                <table class="triage-table wizard-table">
                  <thead>
                    <tr>
                      <th class="col-role">Role</th>
                      <th class="col-pr-num">PR #</th>
                      <th>Title &amp; Branch</th>
                      <th class="col-dates">Dates</th>
                      <th class="col-author">Author</th>
                      <th class="col-blast">Files</th>
                      <th class="col-risk">Risk &amp; Status</th>
                      <th class="col-actions">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${allGroupPRs.map(pr => {
                      const isKeeper = pr.number === keeperNum;
                      const prUrl = this.getPrUrl(pr, activeRepo);
                      const authorInfo = this.getAuthorInfo(pr.author);

                      let actionButton = "";
                      if (isKeeper) {
                        actionButton = `
                          <button class="btn btn-sm btn-primary action-btn" title="Merge Canonical PR" onclick="window.app.openMergeModal(${pr.number})">
                            ⚡ Merge
                          </button>
                        `;
                      } else {
                        actionButton = `
                          <button class="btn btn-sm btn-danger action-btn" title="Close Duplicate PR" onclick="window.app.openCloseModal(${pr.number}, 'Closing as duplicate in favor of PR #${keeperNum} (${this.escapeHtml(group.title)}).')">
                            ✕ Close
                          </button>
                        `;
                      }

                      return `
                        <tr class="${isKeeper ? 'is-keeper-row' : ''}">
                          <td class="col-role">
                            ${isKeeper ? `
                              <span class="badge badge-green">★ KEEP (Winner)</span>
                            ` : `
                              <span class="badge badge-purple">✕ Duplicate</span>
                            `}
                          </td>
                          <td class="col-pr-num">
                            <a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a>
                          </td>
                          <td class="col-title clickable-cell" onclick="window.app.openModal(${pr.number})" title="Click to view details for PR #${pr.number}">
                            <a href="${prUrl}" target="_blank" class="pr-title-link" onclick="event.stopPropagation()">${this.escapeHtml(pr.title)}</a>
                            <div class="pr-meta-line">
                              <span class="pr-branch-name">${this.escapeHtml(pr.branch)}</span>
                            </div>
                            ${!isKeeper ? `
                              <div class="u-text-xs u-text-purple u-mt-2">↳ Superseded by #${keeperNum}</div>
                            ` : ''}
                          </td>
                          <td class="col-dates clickable-cell" onclick="window.app.openModal(${pr.number})" title="Click to view details for PR #${pr.number}">${this.datesCell(pr)}</td>
                          <td class="col-author">${this.authorBadge(pr.author)}</td>
                          <td class="col-blast">${this.blastRadiusCell(pr)}</td>
                          <td class="col-risk">${this.combinedRiskChip(pr)}</td>
                          <td class="col-actions">
                            <div class="actions-cluster">
                              ${actionButton}
                              <button class="btn btn-sm action-btn btn-icon" title="Force Refresh PR #${pr.number} from GitHub" onclick="window.app.forceRefreshSinglePR(${pr.number})">
                                🔄
                              </button>
                              <button class="btn btn-sm action-btn" title="View Details" onclick="window.app.openModal(${pr.number})">
                                ℹ️ More
                              </button>
                            </div>
                          </td>
                        </tr>
                      `;
                    }).join("")}
                  </tbody>
                </table>
              </div>
            </div>
          `;
        }).join("")}
      </div>
    `;
  },

  duplicateClusterCard({ group, keeperNum, selectedDuplicates, allGroupPRs }) {
    const totalInCluster = allGroupPRs.length;
    const toClosePRs = allGroupPRs.filter(p => p.number !== keeperNum);
    const selectedInGroup = toClosePRs.filter(p => selectedDuplicates.has(p.number));
    const isGroupFullySelected = toClosePRs.length > 0 && selectedInGroup.length === toClosePRs.length;
    const isGroupPartiallySelected = selectedInGroup.length > 0 && selectedInGroup.length < toClosePRs.length;

    return `
      <div class="wizard-cluster-card" id="cluster-card-${group.groupId}">
        <div class="wizard-cluster-card-header">
          <div class="cluster-card-title-wrap">
            <strong class="cluster-card-title">${this.escapeHtml(group.title)}</strong>
            <div class="cluster-card-desc">${this.escapeHtml(group.description)}</div>
          </div>
          <div class="u-flex u-gap-10">
            <label class="u-flex u-gap-6 u-cursor-pointer u-text-xs" title="Check to include in batch close, or uncheck to do nothing / skip this group">
              <input type="checkbox" ${isGroupFullySelected || isGroupPartiallySelected ? 'checked' : ''} onchange="window.wizard.toggleGroupDuplicates('${group.groupId}', this.checked)">
              <span class="${isGroupFullySelected ? 'u-text-green u-font-bold' : (selectedInGroup.length === 0 ? 'u-text-muted' : 'u-text-yellow')}">
                ${selectedInGroup.length === 0 ? '⊘ Skipping Group (Do Nothing)' : `Batch Close (${selectedInGroup.length}/${toClosePRs.length})`}
              </span>
            </label>
            <span class="badge badge-purple">${totalInCluster} PRs in cluster</span>
          </div>
        </div>
        
        <div class="wizard-cluster-table-wrap">
          <table class="triage-table wizard-table">
            <thead>
              <tr>
                <th class="col-check">Action</th>
                <th class="col-role">Role</th>
                <th class="col-pr-num">PR #</th>
                <th>Title &amp; Branch</th>
                <th class="col-dates">Dates</th>
                <th class="col-author">Author</th>
                <th class="col-blast">Files</th>
                <th class="col-risk">Risk &amp; Status</th>
              </tr>
            </thead>
            <tbody>
              ${allGroupPRs.map(pr => {
                const isKeeper = pr.number === keeperNum;
                const isChecked = selectedDuplicates.has(pr.number);
                const prUrl = this.getPrUrl(pr);
                return `
                  <tr class="${isKeeper ? 'is-keeper-row' : ''}">
                    <td class="col-check">
                      ${isKeeper ? '<span class="keeper-badge">KEEP</span>' : `
                        <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="window.wizard.toggleDuplicateSelection(${pr.number}, this.checked)">
                      `}
                    </td>
                    <td class="col-role">
                      <label class="keeper-radio-label">
                        <input type="radio" name="keeper_${group.groupId}" value="${pr.number}" ${isKeeper ? 'checked' : ''} onchange="window.wizard.setClusterKeeper('${group.groupId}', ${pr.number})">
                        <span class="${isKeeper ? 'u-font-bold u-text-green' : 'u-text-muted'}">${isKeeper ? 'Keeper' : 'Set Keep'}</span>
                      </label>
                    </td>
                    <td class="col-pr-num">
                      <a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a>
                    </td>
                    <td>
                      <div class="u-font-bold">${this.escapeHtml(pr.title)}</div>
                      <div class="u-font-mono u-text-muted u-text-xs">${this.escapeHtml(pr.branch)}</div>
                    </td>
                    <td class="col-dates">${this.datesCell(pr)}</td>
                    <td class="col-author">${this.authorBadge(pr.author)}</td>
                    <td class="col-blast">${pr.changed_files || 0}</td>
                    <td class="col-risk">${this.combinedRiskChip(pr)}</td>
                  </tr>
                `;
              }).join("")}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  duplicateFlatTable({ groups, selectedDuplicates, clusterKeepers }) {
    const allRows = [];
    groups.forEach(g => {
      const keeperNum = clusterKeepers[g.groupId] || (g.winner ? g.winner.number : null);
      (g.toClose || []).forEach(pr => {
        const isChecked = selectedDuplicates.has(pr.number);
        const prUrl = this.getPrUrl(pr);
        allRows.push(`
          <tr>
            <td class="col-check">
              <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="window.wizard.toggleDuplicateSelection(${pr.number}, this.checked)">
            </td>
            <td class="col-pr-num"><a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a></td>
            <td><strong>${this.escapeHtml(pr.title)}</strong></td>
            <td>
              <span class="badge badge-purple">${this.escapeHtml(g.title)}</span>
              <div class="u-text-xs u-text-green u-mt-2">Superseded by #${keeperNum}</div>
            </td>
            <td>${this.authorBadge(pr.author)}</td>
            <td><code class="u-font-mono u-text-xs">${this.escapeHtml(pr.branch)}</code></td>
            <td>${this.combinedRiskChip(pr)}</td>
          </tr>
        `);
      });
    });

    return `
      <div class="table-container u-mt-14">
        <table class="triage-table">
          <thead>
            <tr>
              <th class="col-check">Close</th>
              <th class="col-pr-num">PR #</th>
              <th>Title</th>
              <th>Cluster / Reason</th>
              <th>Author</th>
              <th>Branch</th>
              <th>Risk &amp; Status</th>
            </tr>
          </thead>
          <tbody>
            ${allRows.join("")}
          </tbody>
        </table>
      </div>
    `;
  },

  /* --- WIZARD VIEWS --- */
  wizardStepTabs({ currentStep, dupCount, mergeCount, conflictCount }) {
    return `
      <div class="wizard-step-tab ${currentStep === 1 ? 'active' : ''}" onclick="window.wizard.goToStep(1)">
        <span class="step-num">1</span> Close Duplicates (${dupCount})
      </div>
      <div class="wizard-step-tab ${currentStep === 2 ? 'active' : ''}" onclick="window.wizard.goToStep(2)">
        <span class="step-num">2</span> Fast-Track Merges (${mergeCount})
      </div>
      <div class="wizard-step-tab ${currentStep === 3 ? 'active' : ''}" onclick="window.wizard.goToStep(3)">
        <span class="step-num">3</span> Rebase &amp; Conflicts (${conflictCount})
      </div>
    `;
  },

  wizardStep1({ groups, selectedDuplicates, clusterKeepers, viewMode, totalDups }) {
    if (!groups || groups.length === 0) {
      return `
        <div class="empty-state wizard-empty-state">
          <h4>🎉 No Duplicate PRs Detected</h4>
          <p>All open pull requests appear unique. You can proceed directly to Step 2.</p>
          <button class="btn btn-primary u-mt-12" onclick="window.wizard.goToStep(2)">Proceed to Step 2 ➔</button>
        </div>
      `;
    }

    let bodyHtml = "";
    if (viewMode === "cluster") {
      bodyHtml = `
        <div class="wizard-clusters-list u-mt-14">
          ${groups.map(g => {
            const keeperNum = clusterKeepers[g.groupId] || (g.winner ? g.winner.number : null);
            const allGroupPRs = [g.winner, ...(g.toClose || [])].filter(Boolean);
            return this.duplicateClusterCard({ group: g, keeperNum, selectedDuplicates, allGroupPRs });
          }).join("")}
        </div>
      `;
    } else {
      bodyHtml = `
        <div class="u-mt-14">
          <div class="u-flex-between u-mb-8">
            <label class="u-flex u-gap-6 u-cursor-pointer u-text-xs">
              <input type="checkbox" checked onchange="window.wizard.toggleAllDuplicates(this.checked)">
              <strong>Select / Deselect All Duplicates (${totalDups})</strong>
            </label>
          </div>
          ${this.duplicateFlatTable({ groups, selectedDuplicates, clusterKeepers })}
        </div>
      `;
    }

    return `
      <div class="wizard-pane-header">
        <div>
          <h3 class="wizard-pane-title">⚪ Step 1: Batch Prune Duplicate Bot &amp; Fragment PRs</h3>
          <p class="wizard-pane-subtitle">
            Identify duplicate Jules/Bot implementations, choose the canonical PR to keep, and close superseded PRs.
          </p>
        </div>
        <div class="view-toggle-group">
          <button class="btn btn-sm ${viewMode === 'cluster' ? 'btn-primary' : ''}" onclick="window.wizard.setDuplicateViewMode('cluster')">🗂️ Cluster View</button>
          <button class="btn btn-sm ${viewMode === 'flat' ? 'btn-primary' : ''}" onclick="window.wizard.setDuplicateViewMode('flat')">📋 Flat List View</button>
        </div>
      </div>

      <div class="wizard-box u-mt-12">
        <label class="wizard-input-label">
          Close Comment Template (supports <code>{keeper_pr}</code>, <code>{cluster_title}</code>):
        </label>
        <input type="text" id="wizardCloseCommentTpl" class="search-input u-w-full u-text-xs" 
          value="Closing as duplicate in favor of PR #{keeper_pr} ({cluster_title}). Cleaned up via PR Triage Command Center.">
      </div>

      ${bodyHtml}

      <div class="wizard-footer">
        <div class="u-flex u-gap-8 u-flex-wrap">
          <button class="btn btn-primary" onclick="window.wizard.executeBatchCloseAPI()" ${totalDups === 0 ? 'disabled' : ''}>
            🗑️ Close ${totalDups} Duplicates via GitHub API
          </button>
          <button class="btn" onclick="window.wizard.copyCloseCommandsCLI()" ${totalDups === 0 ? 'disabled' : ''}>
            📋 Copy CLI Commands
          </button>
          <button class="btn" onclick="window.wizard.downloadCloseScript('ps1')" ${totalDups === 0 ? 'disabled' : ''}>
            💾 Download .ps1 Script
          </button>
        </div>
        <div class="u-flex u-gap-8">
          <button class="btn" onclick="window.wizard.close()">Cancel</button>
          <button class="btn btn-primary" onclick="window.wizard.goToStep(2)">Next: Fast Merges ➔</button>
        </div>
      </div>
    `;
  },

  wizardStep2({ readyPRs, selectedMerges, mergeSortOrder }) {
    const selectedCount = selectedMerges.size;

    return `
      <div class="wizard-pane-header">
        <div>
          <h3 class="wizard-pane-title">🟢 Step 2: Fast-Track Merge Clean &amp; Verified PRs</h3>
          <p class="wizard-pane-subtitle">
            Batch merge zero-conflict PRs in optimal sequence (Lowest blast radius &amp; risk first) to prevent merge conflicts.
          </p>
        </div>
        <div class="u-flex u-gap-8">
          <select id="wizardMergeSortSelect" class="date-select" onchange="window.wizard.setMergeSort(this.value)">
            <option value="risk_asc" ${mergeSortOrder === 'risk_asc' ? 'selected' : ''}>🛡️ Smart Order: Lowest Risk First</option>
            <option value="date_asc" ${mergeSortOrder === 'date_asc' ? 'selected' : ''}>📅 Chronological: Oldest First</option>
            <option value="date_desc" ${mergeSortOrder === 'date_desc' ? 'selected' : ''}>📅 Chronological: Newest First</option>
            <option value="risk_desc" ${mergeSortOrder === 'risk_desc' ? 'selected' : ''}>⚠️ Highest Risk First</option>
          </select>
          <select id="wizardMergeMethod" class="date-select">
            <option value="squash" selected>🔀 Squash and merge</option>
            <option value="merge">🔀 Create merge commit</option>
            <option value="rebase">🔀 Rebase and merge</option>
          </select>
        </div>
      </div>

      <div class="u-mt-14">
        <div class="u-flex-between u-mb-8">
          <label class="u-flex u-gap-6 u-cursor-pointer u-text-xs">
            <input type="checkbox" ${selectedCount === readyPRs.length && readyPRs.length > 0 ? 'checked' : ''} onchange="window.wizard.toggleAllMerges(this.checked)">
            <strong>Select All Ready PRs (<span id="wizardMergeSelectedCount">${selectedCount}</span> of ${readyPRs.length})</strong>
          </label>
        </div>

        <div class="table-container">
          <table class="triage-table">
            <thead>
              <tr>
                <th class="col-check">Merge</th>
                <th class="col-pr-num">PR #</th>
                <th>Title &amp; Subsystems</th>
                <th class="col-dates">Dates</th>
                <th class="col-author">Author</th>
                <th class="col-blast">Files</th>
                <th class="col-risk">Risk &amp; Status</th>
              </tr>
            </thead>
            <tbody>
              ${readyPRs.length === 0 ? `
                <tr><td colspan="7" class="empty-table-cell">No PRs currently in Ready to Merge Hub.</td></tr>
              ` : readyPRs.map(pr => {
                const isChecked = selectedMerges.has(pr.number);
                const prUrl = this.getPrUrl(pr);
                return `
                  <tr>
                    <td class="col-check">
                      <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="window.wizard.toggleMergeSelection(${pr.number}, this.checked)">
                    </td>
                    <td class="col-pr-num"><a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a></td>
                    <td>
                      <div class="u-font-bold">${this.escapeHtml(pr.title)}</div>
                      <div class="u-mt-2">${this.subsystemBadges(pr.subsystems)}</div>
                    </td>
                    <td class="col-dates">${this.datesCell(pr)}</td>
                    <td class="col-author">${this.authorBadge(pr.author)}</td>
                    <td class="col-blast">${pr.changed_files || 0} files</td>
                    <td class="col-risk">${this.combinedRiskChip(pr)}</td>
                  </tr>
                `;
              }).join("")}
            </tbody>
          </table>
        </div>
      </div>

      <div class="wizard-footer">
        <div class="u-flex u-gap-8 u-flex-wrap">
          <button class="btn btn-primary" onclick="window.wizard.executeBatchMergeAPI()" ${selectedCount === 0 ? 'disabled' : ''}>
            🚀 Merge ${selectedCount} PRs via GitHub API
          </button>
          <button class="btn" onclick="window.wizard.copyMergeCommandsCLI()" ${selectedCount === 0 ? 'disabled' : ''}>
            📋 Copy Merge CLI Commands
          </button>
          <button class="btn" onclick="window.wizard.downloadMergeScript('ps1')" ${selectedCount === 0 ? 'disabled' : ''}>
            💾 Download .ps1 Script
          </button>
        </div>
        <div class="u-flex u-gap-8">
          <button class="btn" onclick="window.wizard.goToStep(1)">⬅ Back</button>
          <button class="btn btn-primary" onclick="window.wizard.goToStep(3)">Next: Rebase &amp; Conflicts ➔</button>
        </div>
      </div>
    `;
  },

  wizardStep3({ conflictPRs, selectedConflicts }) {
    const selectedCount = selectedConflicts.size;

    return `
      <div class="wizard-pane-header">
        <div>
          <h3 class="wizard-pane-title">🔴 Step 3: Conflict Resolution &amp; Rebase Center</h3>
          <p class="wizard-pane-subtitle">
            Review conflicting PRs, copy single-branch rebase commands, batch-generate local git rebase scripts, or close obsolete PRs.
          </p>
        </div>
      </div>

      <div class="u-mt-14">
        <div class="u-flex-between u-mb-8">
          <label class="u-flex u-gap-6 u-cursor-pointer u-text-xs">
            <input type="checkbox" ${selectedCount === conflictPRs.length && conflictPRs.length > 0 ? 'checked' : ''} onchange="window.wizard.toggleAllConflicts(this.checked)">
            <strong>Select All Conflicting PRs (<span id="wizardConflictSelectedCount">${selectedCount}</span> of ${conflictPRs.length})</strong>
          </label>
        </div>

        <div class="table-container">
          <table class="triage-table">
            <thead>
              <tr>
                <th class="col-check">Select</th>
                <th class="col-pr-num">PR #</th>
                <th>Title &amp; Branch</th>
                <th class="col-sub-hub">Sub-Hub</th>
                <th class="col-author">Author</th>
                <th class="col-conflict-actions">Resolution Actions</th>
              </tr>
            </thead>
            <tbody>
              ${conflictPRs.length === 0 ? `
                <tr><td colspan="6" class="empty-table-cell">🎉 Zero merge conflicts detected in this repository!</td></tr>
              ` : conflictPRs.map(pr => {
                const isChecked = selectedConflicts.has(pr.number);
                const prUrl = this.getPrUrl(pr);
                return `
                  <tr>
                    <td class="col-check">
                      <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="window.wizard.toggleConflictSelection(${pr.number}, this.checked)">
                    </td>
                    <td class="col-pr-num"><a href="${prUrl}" target="_blank" class="pr-num-link">#${pr.number}</a></td>
                    <td>
                      <div class="u-font-bold">${this.escapeHtml(pr.title)}</div>
                      <div class="u-font-mono u-text-muted u-text-xs u-mt-2">
                        ${this.escapeHtml(pr.branch)} &bull; ${pr.changed_files || 0} files
                      </div>
                    </td>
                    <td class="col-sub-hub">
                      <span class="badge ${pr.sub_hub === 'conflicts_core' ? 'badge-critical' : (pr.sub_hub === 'conflicts_ui' ? 'badge-yellow' : 'badge-gray')}">
                        ${pr.sub_hub === 'conflicts_core' ? '4A Core/DB' : (pr.sub_hub === 'conflicts_ui' ? '4B UI/Planner' : '4C Docs/i18n')}
                      </span>
                    </td>
                    <td class="col-author">${this.authorBadge(pr.author)}</td>
                    <td class="col-conflict-actions">
                      <div class="u-flex u-gap-6">
                        <button class="btn btn-sm action-btn" title="Copy terminal command to merge origin/main into ${pr.branch}" onclick="window.wizard.copySingleRebase('${this.escapeHtml(pr.branch)}')">
                          📋 Copy Rebase
                        </button>
                        <button class="btn btn-sm btn-danger action-btn" onclick="window.app.openCloseModal(${pr.number}, 'Closing due to unresolvable merge conflicts / obsolete PR.')">
                          ✕ Close
                        </button>
                      </div>
                    </td>
                  </tr>
                `;
              }).join("")}
            </tbody>
          </table>
        </div>
      </div>

      <div class="wizard-footer">
        <div class="u-flex u-gap-8 u-flex-wrap">
          <button class="btn btn-primary" onclick="window.wizard.downloadBatchRebaseScript('ps1')" ${selectedCount === 0 ? 'disabled' : ''}>
            💾 Download Batch Rebase .ps1 (${selectedCount} branches)
          </button>
          <button class="btn" onclick="window.wizard.downloadBatchRebaseScript('sh')" ${selectedCount === 0 ? 'disabled' : ''}>
            💾 Download Batch Rebase .sh
          </button>
        </div>
        <div class="u-flex u-gap-8">
          <button class="btn" onclick="window.wizard.goToStep(2)">⬅ Back</button>
          <button class="btn btn-primary" onclick="window.wizard.close()">Finish &amp; Close</button>
        </div>
      </div>
    `;
  },

  /* --- SETTINGS RULE EDITOR CARD --- */
  subsystemRuleCard({ sub, idx, isFirst, isLast }) {
    const keywordsStr = (sub.keywords || []).join(", ");
    return `
      <div class="rule-edit-card" data-idx="${idx}">
        <div class="rule-edit-header">
          <input type="text" class="rule-name-input search-input" value="${this.escapeHtml(sub.name)}" placeholder="Subsystem Name">
          <label class="rule-core-toggle">
            <input type="checkbox" class="rule-core-check" ${sub.isCore ? 'checked' : ''}>
            <strong>Core</strong>
          </label>
          <div class="rule-reorder-buttons">
            <button class="btn btn-sm" onclick="window.app.moveSubsystem(${idx}, -1)" ${isFirst ? 'disabled' : ''} title="Move Up">↑</button>
            <button class="btn btn-sm" onclick="window.app.moveSubsystem(${idx}, 1)" ${isLast ? 'disabled' : ''} title="Move Down">↓</button>
            <button class="btn btn-sm btn-danger" onclick="window.app.deleteSubsystem(${idx})" title="Delete Subsystem">🗑️</button>
          </div>
        </div>
        <div class="rule-keywords-wrap">
          <label class="rule-keywords-label">Keywords / File Path Fragments (comma-separated):</label>
          <input type="text" class="rule-keywords-input search-input" value="${this.escapeHtml(keywordsStr)}">
        </div>
      </div>
    `;
  },

  /* --- LOG ROW --- */
  logRow(entry) {
    let levelClass = "log-level-info";
    if (entry.level === "ERROR") levelClass = "log-level-error";
    else if (entry.level === "WARN") levelClass = "log-level-warn";

    const apiBadge = entry.isApiCall ? `<span class="log-api-pill">API Call (${entry.quota || 'quota'})</span>` : '';
    const durBadge = entry.duration ? `<span class="log-dur-pill">${entry.duration}</span>` : '';

    return `
      <div class="log-row">
        <span class="log-timestamp">${entry.timestamp.substring(11, 23)}</span>
        <strong class="log-level ${levelClass}">${entry.level}</strong>
        <span class="log-component">[${this.escapeHtml(entry.component)}]</span>
        <span class="log-message">${this.escapeHtml(entry.message)}</span>
        ${apiBadge}
        ${durBadge}
      </div>
    `;
  },

  /* --- INFO MODAL CONTENT --- */
  infoModalContent(pr) {
    const files = pr.files || [];
    const changedFiles = pr.changed_files || files.length;
    const notes = pr.notes || [];
    const authorInfo = this.getAuthorInfo(pr.author);
    const prUrl = this.getPrUrl(pr);

    return `
      <div class="info-modal-section">
        <h4 class="info-section-title">Overview</h4>
        <p class="u-text-sm"><strong>PR #${pr.number}:</strong> <a href="${prUrl}" target="_blank" class="pr-title-link">${this.escapeHtml(pr.title)}</a></p>
        <p class="u-text-sm"><strong>Branch:</strong> <code class="u-font-mono">${this.escapeHtml(pr.branch)}</code></p>
        <p class="u-text-sm"><strong>Author:</strong> ${this.authorBadge(pr.author)} (${this.escapeHtml(authorInfo.role)})</p>
        <p class="u-text-sm"><strong>Risk &amp; Status:</strong> ${this.combinedRiskChip(pr)}</p>
        <p class="u-text-sm"><strong>Subsystems:</strong> ${this.subsystemBadges(pr.subsystems)}</p>
        ${pr.risk_reason ? `<p class="u-text-sm"><strong>Risk Rationale:</strong> ${this.escapeHtml(pr.risk_reason)}</p>` : ''}
      </div>

      ${notes.length > 0 ? `
        <div class="info-modal-section">
          <h4 class="info-section-title u-text-red">Merge Conflict / Architecture Notes</h4>
          <ul class="info-notes-list">
            ${notes.map(n => `<li class="info-note-item">⚠️ ${this.escapeHtml(n)}</li>`).join("")}
          </ul>
        </div>
      ` : ''}

      <div class="info-modal-section">
        <h4 class="info-section-title">Files Changed (${changedFiles})</h4>
        ${files.length === 0 ? `
          <div class="u-text-muted u-text-xs">File list not cached. Force refresh this PR to load individual file details.</div>
        ` : `
          <ul class="file-list">
            ${files.map(f => {
              const filename = f.filename || f.path || "file";
              const adds = f.additions || 0;
              const dels = f.deletions || 0;
              return `
                <li class="file-item">
                  <span class="file-item-path">${this.escapeHtml(filename)}</span>
                  <div class="diff-stat">
                    <span class="diff-add">+${adds}</span> / <span class="diff-del">-${dels}</span>
                  </div>
                </li>
              `;
            }).join("")}
          </ul>
        `}
      </div>
    `;
  }
};

window.Templates = Templates;
