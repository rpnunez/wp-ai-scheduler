/**
 * PR Triage Command Center - Triage Wizard Controller
 * Multi-step guided workflow for Duplicate Pruning, Fast-Track Merging, and Conflict Resolution.
 * Pure Controller: delegates all rendering to Templates registry.
 */
class TriageWizard {
  constructor(app) {
    this.app = app;
    this.currentStep = 1;
    this.duplicateViewMode = "cluster"; // 'cluster' | 'flat'
    this.selectedDuplicateNumbers = new Set();
    this.clusterKeepers = {}; // { groupId: winnerPRNumber }
    this.selectedMergeNumbers = new Set();
    this.mergeSortOrder = "risk_asc"; // 'risk_asc' | 'risk_desc' | 'date_asc' | 'date_desc'
    this.selectedConflictNumbers = new Set();
  }

  open() {
    this.currentStep = 1;
    this.initStepData();
    this.render();
    document.getElementById("triageWizardModal").classList.add("open");
    if (window.logger) {
      window.logger.info("Opened Triage Wizard", { component: "Wizard" });
    }
  }

  close() {
    document.getElementById("triageWizardModal").classList.remove("open");
  }

  initStepData() {
    // 1. Duplicates
    this.selectedDuplicateNumbers.clear();
    (this.app.duplicateGroups || []).forEach(g => {
      this.clusterKeepers[g.groupId] = g.winner ? g.winner.number : (g.toClose[0] ? g.toClose[0].number : null);
      (g.toClose || []).forEach(p => this.selectedDuplicateNumbers.add(p.number));
    });

    // 2. Ready to Merge
    this.selectedMergeNumbers.clear();
    const readyPRs = (this.app.prs || []).filter(p => p.hub === "ready_to_merge");
    readyPRs.forEach(p => this.selectedMergeNumbers.add(p.number));

    // 3. Conflicts
    this.selectedConflictNumbers.clear();
    const conflictPRs = (this.app.prs || []).filter(p => p.hub === "needs_rebase");
    conflictPRs.forEach(p => this.selectedConflictNumbers.add(p.number));
  }

  goToStep(step) {
    this.currentStep = step;
    this.render();
  }

  setDuplicateViewMode(mode) {
    this.duplicateViewMode = mode;
    this.render();
  }

  setClusterKeeper(groupId, prNumber) {
    this.clusterKeepers[groupId] = prNumber;
    const group = (this.app.duplicateGroups || []).find(g => g.groupId === groupId);
    if (group) {
      const allGroupPRs = [group.winner, ...(group.toClose || [])].filter(Boolean);
      allGroupPRs.forEach(p => {
        if (p.number === prNumber) {
          this.selectedDuplicateNumbers.delete(p.number);
        } else {
          this.selectedDuplicateNumbers.add(p.number);
        }
      });
    }
    this.render();
  }

  toggleDuplicateSelection(prNumber, isChecked) {
    if (isChecked) {
      this.selectedDuplicateNumbers.add(prNumber);
    } else {
      this.selectedDuplicateNumbers.delete(prNumber);
    }
    this.render();
  }

  toggleGroupDuplicates(groupId, isChecked) {
    const group = (this.app.duplicateGroups || []).find(g => g.groupId === groupId);
    if (!group) return;
    const keeperNum = this.clusterKeepers[groupId] || (group.winner ? group.winner.number : null);
    const allGroupPRs = [group.winner, ...(group.toClose || [])].filter(Boolean);
    
    allGroupPRs.forEach(p => {
      if (p.number !== keeperNum) {
        if (isChecked) {
          this.selectedDuplicateNumbers.add(p.number);
        } else {
          this.selectedDuplicateNumbers.delete(p.number);
        }
      }
    });
    this.render();
  }

  toggleAllDuplicates(isChecked) {
    const allCloseable = [];
    (this.app.duplicateGroups || []).forEach(g => {
      const keeperNum = this.clusterKeepers[g.groupId] || (g.winner ? g.winner.number : null);
      const allGroupPRs = [g.winner, ...(g.toClose || [])].filter(Boolean);
      allGroupPRs.forEach(p => {
        if (p.number !== keeperNum) allCloseable.push(p.number);
      });
    });

    if (isChecked) {
      allCloseable.forEach(num => this.selectedDuplicateNumbers.add(num));
    } else {
      this.selectedDuplicateNumbers.clear();
    }
    this.render();
  }

  toggleMergeSelection(prNumber, isChecked) {
    if (isChecked) this.selectedMergeNumbers.add(prNumber);
    else this.selectedMergeNumbers.delete(prNumber);
    this.render();
  }

  toggleAllMerges(isChecked) {
    const readyPRs = (this.app.prs || []).filter(p => p.hub === "ready_to_merge");
    if (isChecked) {
      readyPRs.forEach(p => this.selectedMergeNumbers.add(p.number));
    } else {
      this.selectedMergeNumbers.clear();
    }
    this.render();
  }

  setMergeSort(order) {
    this.mergeSortOrder = order;
    this.render();
  }

  toggleConflictSelection(prNumber, isChecked) {
    if (isChecked) this.selectedConflictNumbers.add(prNumber);
    else this.selectedConflictNumbers.delete(prNumber);
    this.render();
  }

  toggleAllConflicts(isChecked) {
    const conflictPRs = (this.app.prs || []).filter(p => p.hub === "needs_rebase");
    if (isChecked) {
      conflictPRs.forEach(p => this.selectedConflictNumbers.add(p.number));
    } else {
      this.selectedConflictNumbers.clear();
    }
    this.render();
  }

  render() {
    const headerSteps = document.getElementById("wizardStepNav");
    if (headerSteps) {
      headerSteps.innerHTML = Templates.wizardStepTabs({
        currentStep: this.currentStep,
        dupCount: this.selectedDuplicateNumbers.size,
        mergeCount: this.selectedMergeNumbers.size,
        conflictCount: this.selectedConflictNumbers.size
      });
    }

    const contentContainer = document.getElementById("wizardStepContent");
    if (!contentContainer) return;

    if (this.currentStep === 1) {
      contentContainer.innerHTML = Templates.wizardStep1({
        groups: this.app.duplicateGroups || [],
        selectedDuplicates: this.selectedDuplicateNumbers,
        clusterKeepers: this.clusterKeepers,
        viewMode: this.duplicateViewMode,
        totalDups: this.selectedDuplicateNumbers.size
      });
    } else if (this.currentStep === 2) {
      let readyPRs = (this.app.prs || []).filter(p => p.hub === "ready_to_merge");
      if (this.mergeSortOrder === "risk_asc") {
        readyPRs.sort((a, b) => (a.risk_weight || 1) - (b.risk_weight || 1) || (a.changed_files || 0) - (b.changed_files || 0));
      } else if (this.mergeSortOrder === "risk_desc") {
        readyPRs.sort((a, b) => (b.risk_weight || 1) - (a.risk_weight || 1));
      } else if (this.mergeSortOrder === "date_asc") {
        readyPRs.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
      } else if (this.mergeSortOrder === "date_desc") {
        readyPRs.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
      }

      contentContainer.innerHTML = Templates.wizardStep2({
        readyPRs,
        selectedMerges: this.selectedMergeNumbers,
        mergeSortOrder: this.mergeSortOrder
      });
    } else if (this.currentStep === 3) {
      const conflictPRs = (this.app.prs || []).filter(p => p.hub === "needs_rebase");
      contentContainer.innerHTML = Templates.wizardStep3({
        conflictPRs,
        selectedConflicts: this.selectedConflictNumbers
      });
    }
  }

  /* --- BATCH ACTION DISPATCHERS --- */

  async executeBatchCloseAPI() {
    const toCloseNumbers = Array.from(this.selectedDuplicateNumbers);
    if (toCloseNumbers.length === 0) return;

    const [owner, repo] = this.app.parseRepo(this.app.activeRepo);
    const commentTpl = document.getElementById("wizardCloseCommentTpl") ? document.getElementById("wizardCloseCommentTpl").value : "Closing as duplicate via PR Triage Command Center.";

    if (!confirm(`Are you sure you want to CLOSE ${toCloseNumbers.length} PRs in ${owner}/${repo} using GitHub API?`)) return;

    this.app.showProgress(true, `Closing ${toCloseNumbers.length} duplicate PRs...`);
    let closedCount = 0;
    let failedCount = 0;

    for (let i = 0; i < toCloseNumbers.length; i++) {
      const num = toCloseNumbers[i];
      const group = (this.app.duplicateGroups || []).find(g => (g.toClose || []).some(c => c.number === num));
      const keeperNum = group ? (this.clusterKeepers[group.groupId] || (group.winner ? group.winner.number : 'canonical')) : 'canonical';
      const clusterTitle = group ? group.title : 'Duplicate cluster';

      const comment = commentTpl.replace("{keeper_pr}", keeperNum).replace("{cluster_title}", clusterTitle);

      this.app.updateProgress(Math.round(((i + 1) / toCloseNumbers.length) * 100), `Closing PR #${num} (${i + 1}/${toCloseNumbers.length})...`);
      try {
        await this.app.api.closePR(owner, repo, num, comment);
        closedCount++;
        await new Promise(r => setTimeout(r, 250));
      } catch (err) {
        if (window.logger) window.logger.error(`Failed to close PR #${num}: ${err.message}`, { component: "Wizard" });
        failedCount++;
      }
    }

    this.app.showProgress(false);
    this.app.showToast(`Batch close finished: ${closedCount} closed, ${failedCount} failed.`);
    await this.app.cache.forceRefreshPRs(owner, repo, toCloseNumbers);
    this.app.loadFromCache();
    this.initStepData();
    this.render();
  }

  copyCloseCommandsCLI() {
    const toCloseNumbers = Array.from(this.selectedDuplicateNumbers);
    const commentTpl = document.getElementById("wizardCloseCommentTpl") ? document.getElementById("wizardCloseCommentTpl").value : "Closing as duplicate";

    const lines = [
      "# GitHub CLI Batch Close Script",
      `# Target: ${this.app.activeRepo}`,
      ""
    ];

    toCloseNumbers.forEach(num => {
      const group = (this.app.duplicateGroups || []).find(g => (g.toClose || []).some(c => c.number === num));
      const keeperNum = group ? (this.clusterKeepers[group.groupId] || (group.winner ? group.winner.number : 'canonical')) : 'canonical';
      const clusterTitle = group ? group.title : 'Duplicate cluster';
      const comment = commentTpl.replace("{keeper_pr}", keeperNum).replace("{cluster_title}", clusterTitle);

      lines.push(`gh pr close ${num} --repo ${this.app.activeRepo} --comment "${comment.replace(/"/g, '\\"')}"`);
    });

    this.copyToClipboard(lines.join("\n"), `Copied ${toCloseNumbers.length} 'gh pr close' commands!`);
  }

  downloadCloseScript(ext) {
    const toCloseNumbers = Array.from(this.selectedDuplicateNumbers);
    const commentTpl = document.getElementById("wizardCloseCommentTpl") ? document.getElementById("wizardCloseCommentTpl").value : "Closing as duplicate";

    let content = "";
    if (ext === "ps1") {
      content = `# PR Triage Command Center - Batch Close Script\nWrite-Host "Closing ${toCloseNumbers.length} PRs in ${this.app.activeRepo}..." -ForegroundColor Cyan\n\n`;
      toCloseNumbers.forEach(num => {
        const group = (this.app.duplicateGroups || []).find(g => (g.toClose || []).some(c => c.number === num));
        const keeperNum = group ? (this.clusterKeepers[group.groupId] || (group.winner ? group.winner.number : 'canonical')) : 'canonical';
        const clusterTitle = group ? group.title : 'Duplicate cluster';
        const comment = commentTpl.replace("{keeper_pr}", keeperNum).replace("{cluster_title}", clusterTitle);
        content += `Write-Host "Closing PR #${num}..."\ngh pr close ${num} --repo "${this.app.activeRepo}" --comment "${comment.replace(/"/g, '`"')}"\n`;
      });
      content += `\nWrite-Host "Batch close complete!" -ForegroundColor Green\n`;
    } else {
      content = `#!/usr/bin/env bash\necho "Closing ${toCloseNumbers.length} PRs in ${this.app.activeRepo}..."\n\n`;
      toCloseNumbers.forEach(num => {
        const group = (this.app.duplicateGroups || []).find(g => (g.toClose || []).some(c => c.number === num));
        const keeperNum = group ? (this.clusterKeepers[group.groupId] || (group.winner ? group.winner.number : 'canonical')) : 'canonical';
        const clusterTitle = group ? group.title : 'Duplicate cluster';
        const comment = commentTpl.replace("{keeper_pr}", keeperNum).replace("{cluster_title}", clusterTitle);
        content += `echo "Closing PR #${num}..."\ngh pr close ${num} --repo "${this.app.activeRepo}" --comment "${comment.replace(/"/g, '\\"')}"\n`;
      });
      content += `\necho "Batch close complete!"\n`;
    }

    this.downloadFile(`close_duplicates_${Date.now()}.${ext}`, content);
  }

  async executeBatchMergeAPI() {
    const toMergeNumbers = Array.from(this.selectedMergeNumbers);
    if (toMergeNumbers.length === 0) return;

    const [owner, repo] = this.app.parseRepo(this.app.activeRepo);
    const method = document.getElementById("wizardMergeMethod") ? document.getElementById("wizardMergeMethod").value : "squash";

    if (!confirm(`Are you sure you want to BATCH MERGE ${toMergeNumbers.length} PRs into ${owner}/${repo} using '${method}'?`)) return;

    this.app.showProgress(true, `Merging ${toMergeNumbers.length} PRs sequentially...`);
    let mergedCount = 0;
    let failedCount = 0;

    for (let i = 0; i < toMergeNumbers.length; i++) {
      const num = toMergeNumbers[i];
      const pr = this.app.prs.find(p => p.number === num);
      const title = `${pr ? pr.title : ''} (#${num})`;
      const msg = `Batch merged via PR Triage Command Center Wizard.`;

      this.app.updateProgress(Math.round(((i + 1) / toMergeNumbers.length) * 100), `Merging PR #${num} (${i + 1}/${toMergeNumbers.length})...`);
      try {
        await this.app.api.mergePR(owner, repo, num, method, title, msg);
        mergedCount++;
        await new Promise(r => setTimeout(r, 400));
      } catch (err) {
        if (window.logger) window.logger.error(`Failed to merge PR #${num}: ${err.message}`, { component: "Wizard" });
        failedCount++;
        if (confirm(`PR #${num} could not be merged: ${err.message}.\n\nDo you want to continue merging remaining PRs?`)) {
          continue;
        } else {
          break;
        }
      }
    }

    this.app.showProgress(false);
    this.app.showToast(`Batch merge finished: ${mergedCount} merged, ${failedCount} failed.`);
    await this.app.cache.forceRefreshPRs(owner, repo, toMergeNumbers);
    this.app.loadFromCache();
    this.initStepData();
    this.render();
  }

  copyMergeCommandsCLI() {
    const toMergeNumbers = Array.from(this.selectedMergeNumbers);
    const method = document.getElementById("wizardMergeMethod") ? document.getElementById("wizardMergeMethod").value : "squash";

    const lines = [
      "# GitHub CLI Batch Merge Script",
      `# Target: ${this.app.activeRepo}`,
      ""
    ];

    toMergeNumbers.forEach(num => {
      lines.push(`gh pr merge ${num} --repo ${this.app.activeRepo} --${method} --auto --delete-branch`);
    });

    this.copyToClipboard(lines.join("\n"), `Copied ${toMergeNumbers.length} 'gh pr merge' commands!`);
  }

  downloadMergeScript(ext) {
    const toMergeNumbers = Array.from(this.selectedMergeNumbers);
    const method = document.getElementById("wizardMergeMethod") ? document.getElementById("wizardMergeMethod").value : "squash";

    let content = "";
    if (ext === "ps1") {
      content = `# PR Triage Command Center - Batch Merge Script\nWrite-Host "Merging ${toMergeNumbers.length} PRs in ${this.app.activeRepo} using ${method}..." -ForegroundColor Cyan\n\n`;
      toMergeNumbers.forEach(num => {
        content += `Write-Host "Merging PR #${num}..."\ngh pr merge ${num} --repo "${this.app.activeRepo}" --${method} --delete-branch\n`;
      });
      content += `\nWrite-Host "Batch merge complete!" -ForegroundColor Green\n`;
    } else {
      content = `#!/usr/bin/env bash\necho "Merging ${toMergeNumbers.length} PRs in ${this.app.activeRepo}..."\n\n`;
      toMergeNumbers.forEach(num => {
        content += `echo "Merging PR #${num}..."\ngh pr merge ${num} --repo "${this.app.activeRepo}" --${method} --delete-branch\n`;
      });
      content += `\necho "Batch merge complete!"\n`;
    }

    this.downloadFile(`merge_ready_prs_${Date.now()}.${ext}`, content);
  }

  copySingleRebase(branch) {
    const cmd = `git fetch origin && git checkout ${branch} && git merge origin/main`;
    this.copyToClipboard(cmd, `Copied rebase command for branch '${branch}'!`);
  }

  downloadBatchRebaseScript(ext) {
    const conflictNums = Array.from(this.selectedConflictNumbers);
    const prs = (this.app.prs || []).filter(p => conflictNums.includes(p.number));

    let content = "";
    if (ext === "ps1") {
      content = `# PR Triage Command Center - Batch Rebase Helper Script\nWrite-Host "Fetching latest main..." -ForegroundColor Cyan\ngit fetch origin\n\n`;
      prs.forEach(p => {
        content += `Write-Host "Checking out PR #${p.number} (${p.branch})..." -ForegroundColor Yellow\n`;
        content += `git checkout "${p.branch}"\n`;
        content += `git merge origin/main --no-edit\n`;
        content += `if ($LASTEXITCODE -ne 0) { Write-Host "Conflicts detected on #${p.number}. Resolve manually." -ForegroundColor Red }\n\n`;
      });
      content += `Write-Host "Done iterating through branches." -ForegroundColor Green\n`;
    } else {
      content = `#!/usr/bin/env bash\necho "Fetching latest main..."\ngit fetch origin\n\n`;
      prs.forEach(p => {
        content += `echo "Checking out PR #${p.number} (${p.branch})..."\n`;
        content += `git checkout "${p.branch}"\n`;
        content += `git merge origin/main --no-edit\n\n`;
      });
      content += `echo "Done iterating through branches."\n`;
    }

    this.downloadFile(`rebase_conflicts_${Date.now()}.${ext}`, content);
  }

  copyToClipboard(text, successMsg) {
    navigator.clipboard.writeText(text).then(() => {
      this.app.showToast(successMsg || "Copied to clipboard!");
    }).catch(() => {
      prompt("Copy commands manually:", text);
    });
  }

  downloadFile(filename, text) {
    const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    this.app.showToast(`Downloaded ${filename}`);
  }
}

window.TriageWizard = TriageWizard;
