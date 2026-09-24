/**
 * Duplicate & Competing PR Clustering Engine
 */
class DuplicateClustering {
  static clusterPRs(prs) {
    const duplicateGroups = [];

    const paletteItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("palette") || p.title.toLowerCase().includes("accessibility"));
    const atlasItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("atlas"));
    const boltItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("bolt") || p.title.toLowerCase().includes("n+1"));
    const hunterItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("hunter"));
    const adminIaItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("admin ia") || p.title.toLowerCase().includes("reorganize menu"));
    const promptItems = prs.filter(p => (p.title + p.branch).toLowerCase().includes("prompt-builder") || p.title.toLowerCase().includes("prompt builder"));

    // Admin IA
    if (adminIaItems.length > 1) {
      const winner = adminIaItems.find(p => p.number === 2056) || adminIaItems[0];
      const toClose = adminIaItems.filter(p => p.number !== winner.number);
      duplicateGroups.push({
        groupId: "admin_ia",
        title: "Admin Menu & IA Reorganization",
        description: "Comprehensive 8-hub reorganization supersedes earlier phased schedule PRs.",
        winner,
        toClose
      });
    }

    // Prompt Builders
    if (promptItems.length > 1) {
      const winner = promptItems.find(p => p.number === 2048) || promptItems[0];
      const toClose = promptItems.filter(p => p.number !== winner.number);
      duplicateGroups.push({
        groupId: "prompt_builders",
        title: "Prompt Builders & Shared Marker Architecture",
        description: "Modern marker interface supersedes legacy editable prompt templates.",
        winner,
        toClose
      });
    }

    // Palette a11y Overhaul
    if (paletteItems.length > 1) {
      const winner = paletteItems.find(p => p.number === 1849) || paletteItems[0];
      const toClose = paletteItems.filter(p => p.number !== winner.number);
      duplicateGroups.push({
        groupId: "palette_a11y",
        title: "Palette: Admin UI Accessibility Overhaul (Jules Bot)",
        description: `Comprehensive admin UI accessibility overhaul (PR #${winner.number}, 29 templates) supersedes ${toClose.length} fragmented piecemeal bot PRs.`,
        winner,
        toClose
      });
    }

    // Atlas Refactors
    if (atlasItems.length > 2) {
      const winner = atlasItems.find(p => p.number === 2026) || atlasItems[0];
      const historyFragments = atlasItems.filter(p => [1962, 1935, 1925].includes(p.number));
      if (historyFragments.length) {
        duplicateGroups.push({
          groupId: "atlas_history",
          title: "Atlas: History & Schema Refactoring (Jules Bot Fragments)",
          description: `PR #${winner.number} (Schema) and PR #1897 (History Repo) supersede fragmented partial history extracts from Jules bot.`,
          winner,
          toClose: historyFragments
        });
      }
    }

    // Bolt N+1 Query
    if (boltItems.length > 1) {
      const winner = boltItems.find(p => p.number === 1983) || boltItems[0];
      const toClose = boltItems.filter(p => [1908, 1738].includes(p.number));
      if (toClose.length) {
        duplicateGroups.push({
          groupId: "bolt_queries",
          title: "Bolt: Schedule & Post N+1 Query Optimization (Jules Bot)",
          description: `Comprehensive N+1 query optimization (PR #${winner.number}) supersedes older partial date lookup / query PRs.`,
          winner,
          toClose
        });
      }
    }

    // Hunter Bug Fixes
    if (hunterItems.length > 1) {
      const winner = hunterItems.find(p => p.number === 1642) || hunterItems[0];
      const toClose = hunterItems.filter(p => [1608, 1598].includes(p.number) && p.mergeable === "CONFLICTING");
      if (toClose.length) {
        duplicateGroups.push({
          groupId: "hunter_fixes",
          title: "Hunter: Security & Test Fixes (Jules Bot)",
          description: `PR #${winner.number} unslash fix supersedes conflicting isolated test patches.`,
          winner,
          toClose
        });
      }
    }

    // Map superseded PRs
    const supersededMap = new Map();
    duplicateGroups.forEach(g => {
      g.toClose.forEach(c => {
        supersededMap.set(c.number, {
          targetNumber: g.winner.number,
          groupTitle: g.title
        });
      });
    });

    return {
      duplicateGroups,
      supersededMap
    };
  }

  static assignHubs(prs, duplicateInfo) {
    const { supersededMap } = duplicateInfo;

    return prs.map(pr => {
      const num = pr.number;
      const subs = pr.subsystems || [];

      if (supersededMap.has(num)) {
        const info = supersededMap.get(num);
        return {
          ...pr,
          hub: "duplicate_close",
          hub_name: "Duplicate / Superseded to Close",
          sub_hub: "duplicate_close",
          recommended_action: `Close as duplicate of #${info.targetNumber} in ${info.groupTitle}`
        };
      }

      if (pr.mergeable === "CONFLICTING") {
        let subHub = "conflicts_general";
        let subHubName = "🟡 Sub-Hub 4C: Localization, Documentation & Testing Conflicts";

        if (subs.includes("Database & Schema") || subs.includes("Generation Pipeline") || subs.includes("Cron Engine") || pr.risk_level === "CRITICAL") {
          subHub = "conflicts_core";
          subHubName = "🔴 Sub-Hub 4A: Database, Cron & Core Generation Engine Conflicts";
        } else if (subs.includes("Admin UI & Planner") || subs.includes("AJAX & Controllers") || subs.includes("Repositories & Entities")) {
          subHub = "conflicts_ui";
          subHubName = "🟠 Sub-Hub 4B: Admin UI, Planner, Repositories & AJAX Conflicts";
        }

        return {
          ...pr,
          hub: "needs_rebase",
          hub_name: "Merge Conflicts (Needs Rebase & Conflict Fix)",
          sub_hub: subHub,
          sub_hub_name: subHubName,
          recommended_action: "Rebase on main & resolve merge conflicts"
        };
      }

      if (pr.risk_level === "HIGH" || pr.risk_level === "CRITICAL") {
        return {
          ...pr,
          hub: "requires_review",
          hub_name: "Requires Spec & Architecture Review",
          sub_hub: "requires_review",
          recommended_action: `Architecture review & integration tests (${pr.risk_level} Risk)`
        };
      }

      return {
        ...pr,
        hub: "ready_to_merge",
        hub_name: "Ready to Merge (Fast-Track)",
        sub_hub: "ready_to_merge",
        recommended_action: "Ready to merge (Clean & verified)"
      };
    });
  }
}

window.DuplicateClustering = DuplicateClustering;
