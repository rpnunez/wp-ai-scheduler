/**
 * Subsystem Classifier & Multi-Dimensional Risk Scorer
 */
class Classifier {
  static getRules() {
    return {
      subsystems: (window.AppConfig && window.AppConfig.subsystemRules) || [],
      riskRules: (window.AppConfig && window.AppConfig.riskRules) || {
        largeFileThreshold: 15,
        maxCriticalRiskWhenCoreAndDB: true,
        highRiskLinesChangedThreshold: 500
      }
    };
  }

  static classifySubsystems(files, title, branch) {
    const subsystems = new Set();
    const filePaths = (files || []).map(f => (f.path || f.filename || "").toLowerCase());
    
    let hasDB = false;
    let hasGeneration = false;
    let hasCron = false;

    const { subsystems: rules } = this.getRules();

    rules.forEach(rule => {
      let matched = false;
      for (const kw of (rule.keywords || [])) {
        if (!kw) continue;
        const lowerKw = kw.toLowerCase().trim();
        if (filePaths.some(p => p.includes(lowerKw))) {
          matched = true;
          break;
        }
      }
      if (matched) {
        subsystems.add(rule.name);
        if (rule.name === "Database & Schema" || rule.isCore) {
          if (rule.name === "Database & Schema") hasDB = true;
          if (rule.name === "Generation Pipeline") hasGeneration = true;
          if (rule.name === "Cron Engine") hasCron = true;
        }
      }
    });

    // Fallback title / branch text
    const cleanText = `${title || ""} ${branch || ""}`.toLowerCase().replace("nunezscheduler", "").replace("ai-post-scheduler", "");
    if (cleanText.includes("db") || cleanText.includes("database") || cleanText.includes("schema")) {
      subsystems.add("Database & Schema");
      hasDB = true;
    }
    if (cleanText.includes("planner") || cleanText.includes("ui") || cleanText.includes("menu")) {
      subsystems.add("Admin UI & Planner");
    }
    if (cleanText.includes("i18n") || cleanText.includes("translation") || cleanText.includes("l10n")) {
      subsystems.add("Localization & i18n");
    }
    if (cleanText.includes("cron") || cleanText.includes("schedule")) {
      subsystems.add("Cron Engine");
      hasCron = true;
    }
    if (cleanText.includes("prompt") || cleanText.includes("generation") || cleanText.includes("model")) {
      subsystems.add("Generation Pipeline");
      hasGeneration = true;
    }

    if (subsystems.size === 0) {
      subsystems.add("Core Architecture");
    }

    return {
      subsystems: Array.from(subsystems).sort(),
      hasDB,
      hasGeneration,
      hasCron
    };
  }

  static computeRisk(pr, subInfo) {
    const changedFiles = pr.changed_files || (pr.files ? pr.files.length : 0);
    const totalLines = (pr.additions || 0) + (pr.deletions || 0);
    const { subsystems, hasDB, hasGeneration, hasCron } = subInfo;
    const { riskRules } = this.getRules();

    // Rule: Core Engine + DB Schema = CRITICAL
    if (riskRules.maxCriticalRiskWhenCoreAndDB && hasDB && (hasGeneration || hasCron)) {
      return { level: "CRITICAL", weight: 4, reason: "Touches both Core Engine (Generation/Cron) and Database Schema." };
    }

    // Rule: Large file blast radius
    const threshold = riskRules.largeFileThreshold || 15;
    if (changedFiles > threshold) {
      return { level: "HIGH", weight: 3, reason: `Large blast radius (${changedFiles} files changed).` };
    }

    // Rule: Very high line changes
    const lineThreshold = riskRules.highRiskLinesChangedThreshold || 500;
    if (totalLines > lineThreshold && changedFiles > 5) {
      return { level: "HIGH", weight: 3, reason: `Substantial diff size (+${pr.additions || 0}/-${pr.deletions || 0} lines across ${changedFiles} files).` };
    }

    // Rule: Core components = HIGH
    if (hasDB) return { level: "HIGH", weight: 3, reason: "Modifies Database schema, tables, or installer." };
    if (hasGeneration) return { level: "HIGH", weight: 3, reason: "Modifies AI Generation Pipeline / Prompt assembly." };
    if (hasCron) return { level: "HIGH", weight: 3, reason: "Modifies background worker or cron scheduling engine." };

    // Rule: UI / Controllers / Repositories
    const hasUI = subsystems.some(s => ["Admin UI & Planner", "AJAX & Controllers", "Repositories & Entities"].includes(s));
    if (hasUI) {
      if (changedFiles >= 3) {
        return { level: "MEDIUM", weight: 2, reason: `Modifies UI / Controllers / Repositories across ${changedFiles} files.` };
      } else {
        return { level: "LOW", weight: 1, reason: `Targeted UI / Controller fix (${changedFiles} files changed).` };
      }
    }

    // Rule: Safe subsystems (Docs, Tests, i18n)
    const isSafeSubsystem = subsystems.every(s => ["Documentation", "Tests & CI", "Localization & i18n", "Logging & Telemetry"].includes(s));
    if (isSafeSubsystem || changedFiles <= 2) {
      return { level: "LOW", weight: 1, reason: `Scoped small change (${changedFiles} files changed).` };
    }

    return { level: "MEDIUM", weight: 2, reason: `Standard modification (${changedFiles} files changed).` };
  }

  static enrichPR(pr) {
    const subInfo = this.classifySubsystems(pr.files, pr.title, pr.branch);
    const risk = this.computeRisk(pr, subInfo);

    const notes = [];
    if (pr.mergeable === "CONFLICTING" || pr.merge_state === "dirty") {
      pr.mergeable = "CONFLICTING";
      if (pr.number === 1962) notes.push("Conflict on ai-post-scheduler/includes/class-aips-history-repository.php");
      else if (pr.number === 1983) notes.push("Conflict on .build/bolt.md and class-aips-generated-posts-controller.php");
      else notes.push("Branch has merge conflicts against base branch (main)");
    }

    return {
      ...pr,
      subsystems: subInfo.subsystems,
      risk_level: risk.level,
      risk_weight: risk.weight,
      risk_reason: risk.reason,
      notes: notes
    };
  }
}

window.Classifier = Classifier;
