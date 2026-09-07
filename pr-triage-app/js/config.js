/**
 * PR Triage Command Center - Configuration & Settings
 */
const DEFAULT_SUBSYSTEM_RULES = [
  {
    name: "Database & Schema",
    keywords: ["db-manager", "class-aips-db", "migrations/", "installer", "schema", "table", "database", "migration", "batch_run table"],
    isCore: true
  },
  {
    name: "Generation Pipeline",
    keywords: ["generation", "ai-engine", "meow", "prompt-", "prompt_", "prompt", "content-generator", "post-builder", "context_factory", "template-context", "topic-context", "model"],
    isCore: true
  },
  {
    name: "Cron Engine",
    keywords: ["class-aips-cron", "class-aips-task-runner", "class-aips-scheduler.php", "background-process", "cron-worker", "recurring schedule", "cron"],
    isCore: true
  },
  {
    name: "Admin UI & Planner",
    keywords: ["assets/js/", "assets/css/", "templates/admin/", "planner", "admin-bar", "class-aips-admin", "ui", "admin ia", "modal", "css", "layout", "menu", "admin menu"]
  },
  {
    name: "AJAX & Controllers",
    keywords: ["ajax", "controller", "class-aips-ajax"]
  },
  {
    name: "Repositories & Entities",
    keywords: ["repository", "repositories", "class-aips-entity-"]
  },
  {
    name: "Localization & i18n",
    keywords: ["class-aips-admin-l10n", "class-aips-language-store", "languages/", ".pot", ".po", "i18n", "translation", "l10n", "gettext", "wp_set_script_translations"]
  },
  {
    name: "Logging & Telemetry",
    keywords: ["class-aips-logger", "history", "correlation-id", "telemetry"]
  },
  {
    name: "Tests & CI",
    keywords: ["tests/", "test-", "phpunit", "test", "tests"]
  },
  {
    name: "Documentation",
    keywords: ["docs/", "readme.md", "changelog.md", "agents.md", ".github/", "doc", "docs", "readme", "changelog", "guidelines"]
  }
];

const DEFAULT_RISK_RULES = {
  largeFileThreshold: 15,
  maxCriticalRiskWhenCoreAndDB: true,
  highRiskLinesChangedThreshold: 500
};

const AppConfig = {
  appName: "PR Triage Command Center",
  version: "2.2.0",
  defaultRepo: "rpnunez/wp-ai-scheduler",
  
  storageKeys: {
    githubToken: "pr_triage_pat",
    activeRepo: "pr_triage_active_repo",
    theme: "pr_triage_theme",
    cachePrefix: "pr_triage_cache_",
    cachedRepos: "pr_triage_user_repos",
    collapsedHubs: "pr_triage_collapsed_hubs",
    syncMode: "pr_triage_sync_mode",
    customRules: "pr_triage_custom_rules",
    execMode: "pr_triage_exec_mode"
  },

  syncMode: "graphql",
  defaultSubsystemRules: DEFAULT_SUBSYSTEM_RULES,
  defaultRiskRules: DEFAULT_RISK_RULES,

  getSubsystemRules() {
    try {
      const saved = localStorage.getItem(this.storageKeys.customRules);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.subsystems && Array.isArray(parsed.subsystems) && parsed.subsystems.length > 0) {
          return parsed.subsystems;
        }
      }
    } catch (e) {
      console.warn("Error reading custom subsystem rules from storage:", e);
    }
    return JSON.parse(JSON.stringify(this.defaultSubsystemRules));
  },

  getRiskRules() {
    try {
      const saved = localStorage.getItem(this.storageKeys.customRules);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.riskRules && typeof parsed.riskRules === "object") {
          return { ...this.defaultRiskRules, ...parsed.riskRules };
        }
      }
    } catch (e) {
      console.warn("Error reading custom risk rules from storage:", e);
    }
    return { ...this.defaultRiskRules };
  },

  saveCustomRules(subsystems, riskRules) {
    const payload = {
      subsystems: subsystems || this.getSubsystemRules(),
      riskRules: riskRules || this.getRiskRules(),
      updatedAt: new Date().toISOString()
    };
    localStorage.setItem(this.storageKeys.customRules, JSON.stringify(payload));
    this.subsystemRules = payload.subsystems;
    this.riskRules = payload.riskRules;
    if (window.logger) {
      window.logger.info("Custom classification and risk rules updated", { component: "Config" });
    }
  },

  resetCustomRules() {
    localStorage.removeItem(this.storageKeys.customRules);
    this.subsystemRules = JSON.parse(JSON.stringify(this.defaultSubsystemRules));
    this.riskRules = { ...this.defaultRiskRules };
    if (window.logger) {
      window.logger.info("Reset classification & risk rules to factory defaults", { component: "Config" });
    }
  },

  getExecMode() {
    return localStorage.getItem(this.storageKeys.execMode) || "api"; // 'api' | 'cli'
  },

  setExecMode(mode) {
    localStorage.setItem(this.storageKeys.execMode, mode);
  }
};

// Initialize active in-memory rules from storage or defaults
AppConfig.subsystemRules = AppConfig.getSubsystemRules();
AppConfig.riskRules = AppConfig.getRiskRules();

window.AppConfig = AppConfig;
