/**
 * Differential Caching Layer with Logger & Turbo-Sync Support
 */
class CacheStore {
  constructor(api) {
    this.api = api;
  }

  getCacheKey(owner, repo) {
    return `${AppConfig.storageKeys.cachePrefix}${owner}_${repo}`.toLowerCase();
  }

  getCache(owner, repo) {
    const key = this.getCacheKey(owner, repo);
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : { prs: {}, lastSync: null };
    } catch (e) {
      window.logger.warn(`Error reading cache for ${owner}/${repo}: ${e.message}`, { component: "CacheStore" });
      return { prs: {}, lastSync: null };
    }
  }

  saveCache(owner, repo, cacheData) {
    const key = this.getCacheKey(owner, repo);
    try {
      localStorage.setItem(key, JSON.stringify(cacheData));
      window.logger.debug(`Saved cache for ${owner}/${repo} (${Object.keys(cacheData.prs || {}).length} PRs)`, { component: "CacheStore" });
    } catch (e) {
      window.logger.error(`Failed to save cache for ${owner}/${repo} (quota full?): ${e.message}`, { component: "CacheStore" });
    }
  }

  clearCache(owner, repo) {
    const key = this.getCacheKey(owner, repo);
    localStorage.removeItem(key);
    window.logger.info(`Cleared cache for ${owner}/${repo}`, { component: "CacheStore" });
  }

  async syncRepository(owner, repo, onProgress) {
    const syncMode = localStorage.getItem(AppConfig.storageKeys.syncMode) || AppConfig.syncMode;

    if (syncMode === "graphql" && this.api.hasToken()) {
      if (onProgress) onProgress({ phase: "graphql_sync", message: "Turbo-syncing 100 PRs via GitHub GraphQL API (1 call)..." });
      try {
        const prsList = await this.api.fetchPRsGraphQL(owner, repo);
        const cachedPRs = {};
        prsList.forEach(p => {
          cachedPRs[p.number] = p;
        });

        const newCache = {
          prs: cachedPRs,
          lastSync: new Date().toISOString()
        };

        this.saveCache(owner, repo, newCache);
        window.logger.info(`GraphQL sync complete: ${prsList.length} PRs updated`, { component: "CacheStore" });
        return Object.values(cachedPRs);
      } catch (gqlErr) {
        window.logger.warn(`GraphQL sync fallback to REST: ${gqlErr.message}`, { component: "CacheStore" });
      }
    }

    const cache = this.getCache(owner, repo);
    const cachedPRs = cache.prs || {};

    if (onProgress) onProgress({ phase: "fetching_list", message: "Fetching open PR index from GitHub..." });
    const openSummaries = await this.api.fetchOpenPRSummaries(owner, repo);

    const openNumbers = new Set(openSummaries.map(s => s.number));
    const prsToFetch = [];

    openSummaries.forEach(summary => {
      const num = summary.number;
      const cached = cachedPRs[num];
      if (!cached || cached.updated_at !== summary.updated_at || !cached.mergeable || cached.mergeable === "UNKNOWN") {
        prsToFetch.push(summary);
      }
    });

    window.logger.info(`Differential sync: ${openSummaries.length} total open PRs, ${openSummaries.length - prsToFetch.length} cached hits, ${prsToFetch.length} need fetch`, {
      component: "CacheStore",
      meta: { openCount: openSummaries.length, toFetch: prsToFetch.length }
    });

    if (onProgress) {
      onProgress({
        phase: "diff_check",
        totalOpen: openSummaries.length,
        cachedCount: openSummaries.length - prsToFetch.length,
        toFetchCount: prsToFetch.length,
        message: `Found ${openSummaries.length} PRs (${openSummaries.length - prsToFetch.length} up-to-date, ${prsToFetch.length} need sync).`
      });
    }

    const concurrency = 6;
    for (let i = 0; i < prsToFetch.length; i += concurrency) {
      const chunk = prsToFetch.slice(i, i + concurrency);
      await Promise.all(chunk.map(async (summary) => {
        try {
          const details = await this.api.fetchPRDetails(owner, repo, summary.number);
          cachedPRs[summary.number] = this.formatPRData(details);
        } catch (err) {
          window.logger.error(`Failed to fetch PR #${summary.number}: ${err.message}`, { component: "CacheStore" });
        }
      }));

      if (onProgress) {
        const completed = Math.min(i + concurrency, prsToFetch.length);
        onProgress({
          phase: "fetching_details",
          completed,
          total: prsToFetch.length,
          message: `Fetched PR details: ${completed}/${prsToFetch.length}`
        });
      }
    }

    Object.keys(cachedPRs).forEach(numStr => {
      const num = parseInt(numStr, 10);
      if (!openNumbers.has(num)) {
        delete cachedPRs[numStr];
        window.logger.debug(`Purged closed PR #${num} from cache`, { component: "CacheStore" });
      }
    });

    const newCache = {
      prs: cachedPRs,
      lastSync: new Date().toISOString()
    };

    this.saveCache(owner, repo, newCache);
    return Object.values(cachedPRs);
  }

  async forceRefreshPRs(owner, repo, prNumbers, onProgress) {
    const cache = this.getCache(owner, repo);
    const cachedPRs = cache.prs || {};

    window.logger.info(`Force-refreshing ${prNumbers.length} specific PRs: ${prNumbers.join(", ")}`, {
      component: "CacheStore",
      meta: { prNumbers }
    });

    for (let i = 0; i < prNumbers.length; i++) {
      const num = prNumbers[i];
      try {
        const details = await this.api.fetchPRDetails(owner, repo, num);
        if (details.state === "closed") {
          delete cachedPRs[num];
          window.logger.info(`PR #${num} is closed -> removed from cache`, { component: "CacheStore" });
        } else {
          cachedPRs[num] = this.formatPRData(details);
          window.logger.info(`Updated cache for PR #${num}`, { component: "CacheStore" });
        }
      } catch (err) {
        window.logger.warn(`Could not force refresh PR #${num}: ${err.message}`, { component: "CacheStore" });
        delete cachedPRs[num];
      }

      if (onProgress) {
        onProgress({
          phase: "force_fetching",
          completed: i + 1,
          total: prNumbers.length,
          message: `Refreshed PR #${num} (${i + 1}/${prNumbers.length})`
        });
      }
    }

    this.saveCache(owner, repo, { prs: cachedPRs, lastSync: new Date().toISOString() });
    return Object.values(cachedPRs);
  }

  formatPRData(raw) {
    const files = (raw.files || []).map(f => ({
      path: f.filename || f.path || "",
      additions: f.additions || 0,
      deletions: f.deletions || 0,
      status: f.status || "modified"
    }));

    const authorLogin = raw.user?.login || (typeof raw.author === "string" ? raw.author : raw.author?.login) || "unknown";
    let botType = authorLogin;
    if (authorLogin.includes("jules")) botType = "Google Jules Bot";
    else if (authorLogin.includes("copilot")) botType = "GitHub Copilot Bot";
    else if (authorLogin === "rpnunez") botType = "Human (rpnunez)";

    let mergeable = "UNKNOWN";
    if (raw.mergeable === true) mergeable = "MERGEABLE";
    else if (raw.mergeable === false || raw.mergeable_state === "dirty") mergeable = "CONFLICTING";

    const labelsList = (raw.labels || []).map(l => typeof l === "string" ? l : (l.name || ""));

    return {
      number: raw.number,
      title: raw.title || "",
      body: raw.body || "",
      branch: raw.head?.ref || raw.headRefName || "",
      base_branch: raw.base?.ref || raw.baseRefName || "main",
      author: authorLogin,
      bot_type: botType,
      labels: labelsList,
      is_draft: !!raw.draft,
      mergeable: mergeable,
      merge_state: raw.mergeable_state || "unknown",
      changed_files: raw.changed_files !== undefined ? raw.changed_files : files.length,
      additions: raw.additions || 0,
      deletions: raw.deletions || 0,
      created_at: raw.created_at || raw.createdAt || "",
      updated_at: raw.updated_at || raw.updatedAt || "",
      files: files,
      url: raw.html_url || raw.url || `https://github.com/${raw.head?.repo?.full_name || ""}/pull/${raw.number}`
    };
  }
}

window.CacheStore = CacheStore;
