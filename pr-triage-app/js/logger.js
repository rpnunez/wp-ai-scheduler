/**
 * Structured Session Logger & File Exporter
 */
class SessionLogger {
  constructor() {
    this.sessionId = Math.random().toString(36).substring(2, 9);
    this.startTime = new Date();
    this.logs = [];
    this.maxLogs = 2000;
    this.enabled = true;
    this.minLevel = "DEBUG"; // DEBUG, INFO, WARN, ERROR
    
    this.levelWeights = {
      DEBUG: 0,
      INFO: 1,
      WARN: 2,
      ERROR: 3
    };

    this.log("INFO", "Session started", {
      component: "System",
      sessionId: this.sessionId,
      startTime: this.startTime.toISOString()
    });
  }

  log(level, message, options = {}) {
    if (!this.enabled) return;
    if (this.levelWeights[level] < this.levelWeights[this.minLevel]) return;

    const timestamp = new Date().toISOString();
    const isApiCall = !!options.isApiCall;
    const component = options.component || "App";
    const quota = options.quota || null;
    const meta = options.meta || null;
    const duration = options.durationMs !== undefined ? `${options.durationMs}ms` : null;

    const entry = {
      id: this.logs.length + 1,
      timestamp,
      level,
      sessionId: this.sessionId,
      component,
      message,
      isApiCall,
      quota,
      duration,
      meta
    };

    this.logs.push(entry);
    if (this.logs.length > this.maxLogs) {
      this.logs.shift();
    }

    // Format for console & file
    const apiTag = isApiCall ? `[API_CALL: YES | Quota: ${quota || "N/A"}]` : "[API_CALL: NO]";
    const durTag = duration ? ` (${duration})` : "";
    const logString = `[${timestamp}] [${level}] [SESSION: ${this.sessionId}] ${apiTag} [${component}] ${message}${durTag}`;

    if (level === "ERROR") console.error(logString, meta || "");
    else if (level === "WARN") console.warn(logString, meta || "");
    else console.log(logString);

    if (window.onLogEntry) {
      window.onLogEntry(entry);
    }
  }

  debug(msg, opts) { this.log("DEBUG", msg, opts); }
  info(msg, opts) { this.log("INFO", msg, opts); }
  warn(msg, opts) { this.log("WARN", msg, opts); }
  error(msg, opts) { this.log("ERROR", msg, opts); }

  getLogs() {
    return this.logs;
  }

  clearLogs() {
    this.logs = [];
    this.info("Session logs cleared", { component: "Logger" });
  }

  exportLogFile(format = "log") {
    let content = "";
    if (format === "json") {
      content = JSON.stringify(this.logs, null, 2);
    } else {
      content = `# PR Triage Command Center - Session Log File\n# Session ID: ${this.sessionId}\n# Exported At: ${new Date().toISOString()}\n# Total Entries: ${this.logs.length}\n# ===========================================================================\n\n` +
        this.logs.map(e => {
          const apiTag = e.isApiCall ? `[API_CALL: YES | Quota: ${e.quota || "N/A"}]` : "[API_CALL: NO]";
          const durTag = e.duration ? ` (${e.duration})` : "";
          const metaStr = e.meta ? ` | Meta: ${JSON.stringify(e.meta)}` : "";
          return `[${e.timestamp}] [${e.level.padEnd(5)}] [SESSION: ${e.sessionId}] ${apiTag} [${e.component}] ${e.message}${durTag}${metaStr}`;
        }).join("\n");
    }

    const blob = new Blob([content], { type: format === "json" ? "application/json" : "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const dateStr = new Date().toISOString().substring(0, 10);
    a.href = url;
    a.download = `pr-triage-session-${dateStr}-${this.sessionId}.${format === "json" ? "json" : "log"}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    this.info(`Session log exported as .${format}`, { component: "Logger" });
  }
}

window.logger = new SessionLogger();
