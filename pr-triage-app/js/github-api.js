/**
 * GitHub API Client (REST & GraphQL Turbo-Sync) with Live Logging
 */
class GitHubApi {
  constructor() {
    this.token = localStorage.getItem(AppConfig.storageKeys.githubToken) || "";
    this.rateLimit = {
      limit: 5000,
      remaining: 5000,
      reset: 0,
      resetDate: null
    };
  }

  setToken(token) {
    this.token = token.trim();
    if (this.token) {
      localStorage.setItem(AppConfig.storageKeys.githubToken, this.token);
      window.logger.info("GitHub PAT token updated", { component: "GitHubApi" });
    } else {
      localStorage.removeItem(AppConfig.storageKeys.githubToken);
      window.logger.info("GitHub PAT token removed", { component: "GitHubApi" });
    }
  }

  getToken() { return this.token; }
  hasToken() { return !!this.token; }

  async request(endpoint, options = {}) {
    const url = endpoint.startsWith("http") ? endpoint : `https://api.github.com${endpoint}`;
    const headers = {
      "Accept": "application/vnd.github.v3+json",
      ...(options.headers || {})
    };

    if (this.token) {
      headers["Authorization"] = `Bearer ${this.token}`;
    }

    const startTime = performance.now();
    let response;
    try {
      response = await fetch(url, { ...options, headers });
    } catch (netErr) {
      window.logger.error(`Network error requesting ${endpoint}: ${netErr.message}`, {
        component: "GitHubApi",
        isApiCall: true,
        meta: { url, error: netErr.message }
      });
      throw netErr;
    }

    const durationMs = Math.round(performance.now() - startTime);

    const limit = response.headers.get("x-ratelimit-limit");
    const remaining = response.headers.get("x-ratelimit-remaining");
    const reset = response.headers.get("x-ratelimit-reset");

    if (limit && remaining) {
      this.rateLimit = {
        limit: parseInt(limit, 10),
        remaining: parseInt(remaining, 10),
        reset: parseInt(reset, 10),
        resetDate: new Date(parseInt(reset, 10) * 1000)
      };
      if (window.onRateLimitUpdate) {
        window.onRateLimitUpdate(this.rateLimit);
      }
    }

    const quotaStr = remaining ? `${remaining}/${limit}` : "N/A";
    const method = options.method || "GET";

    if (!response.ok) {
      const errBody = await response.json().catch(() => ({}));
      const errMsg = errBody.message || `${response.status} ${response.statusText}`;
      window.logger.error(`${method} ${endpoint} failed (${response.status}): ${errMsg}`, {
        component: "GitHubApi",
        isApiCall: true,
        quota: quotaStr,
        durationMs,
        meta: { status: response.status, body: errBody }
      });
      throw new Error(errMsg);
    }

    window.logger.info(`${method} ${endpoint} (200 OK)`, {
      component: "GitHubApi",
      isApiCall: true,
      quota: quotaStr,
      durationMs
    });

    return response.json();
  }

  async getRateLimit() {
    return this.request("/rate_limit");
  }

  async getUserRepos() {
    if (!this.token) return [];
    return this.request("/user/repos?per_page=100&sort=updated");
  }

  async fetchOpenPRSummaries(owner, repo) {
    let allPRs = [];
    let page = 1;
    while (true) {
      const pagePRs = await this.request(`/repos/${owner}/${repo}/pulls?state=open&per_page=100&page=${page}`);
      if (!pagePRs || pagePRs.length === 0) break;
      allPRs = allPRs.concat(pagePRs);
      if (pagePRs.length < 100) break;
      page++;
    }
    return allPRs;
  }

  async fetchPRDetails(owner, repo, prNumber) {
    const prDetails = await this.request(`/repos/${owner}/${repo}/pulls/${prNumber}`);
    const files = await this.request(`/repos/${owner}/${repo}/pulls/${prNumber}/files?per_page=100`);
    return {
      ...prDetails,
      files: files || []
    };
  }

  async fetchPRsGraphQL(owner, repo) {
    if (!this.token) {
      throw new Error("GraphQL sync requires a GitHub PAT token.");
    }

    const query = `
      query GetRepoPRs($owner: String!, $repo: String!) {
        repository(owner: $owner, name: $repo) {
          pullRequests(states: OPEN, first: 100, orderBy: { field: UPDATED_AT, direction: DESC }) {
            nodes {
              number
              title
              body
              url
              isDraft
              createdAt
              updatedAt
              mergeable
              mergeStateStatus
              additions
              deletions
              changedFiles
              headRefName
              baseRefName
              author {
                login
              }
              labels(first: 20) {
                nodes {
                  name
                }
              }
              files(first: 100) {
                nodes {
                  path
                  additions
                  deletions
                  changeType
                }
              }
            }
          }
        }
      }
    `;

    const data = await this.request("/graphql", {
      method: "POST",
      body: JSON.stringify({
        query,
        variables: { owner, repo }
      })
    });

    const nodes = data?.data?.repository?.pullRequests?.nodes || [];
    window.logger.info(`GraphQL Turbo-Sync fetched ${nodes.length} open PRs in 1 request!`, {
      component: "GitHubApi",
      isApiCall: true,
      meta: { count: nodes.length }
    });

    return nodes.map(n => {
      let mergeableStr = "UNKNOWN";
      if (n.mergeable === "MERGEABLE") mergeableStr = "MERGEABLE";
      else if (n.mergeable === "CONFLICTING" || n.mergeStateStatus === "DIRTY") mergeableStr = "CONFLICTING";

      const authorLogin = n.author?.login || "unknown";
      let botType = authorLogin;
      if (authorLogin.includes("jules")) botType = "Google Jules Bot";
      else if (authorLogin.includes("copilot")) botType = "GitHub Copilot Bot";
      else if (authorLogin === "rpnunez") botType = "Human (rpnunez)";

      const filesList = (n.files?.nodes || []).map(f => ({
        path: f.path,
        additions: f.additions,
        deletions: f.deletions,
        status: f.changeType?.toLowerCase() || "modified"
      }));

      const labelsList = (n.labels?.nodes || []).map(l => l.name);

      return {
        number: n.number,
        title: n.title,
        body: n.body || "",
        branch: n.headRefName,
        base_branch: n.baseRefName || "main",
        author: authorLogin,
        bot_type: botType,
        labels: labelsList,
        is_draft: !!n.isDraft,
        mergeable: mergeableStr,
        merge_state: n.mergeStateStatus || "unknown",
        changed_files: n.changedFiles || filesList.length,
        additions: n.additions || 0,
        deletions: n.deletions || 0,
        created_at: n.createdAt,
        updated_at: n.updatedAt,
        files: filesList,
        url: n.url
      };
    });
  }

  async mergePR(owner, repo, prNumber, mergeMethod = "squash", commitTitle = "", commitMessage = "") {
    window.logger.info(`Executing merge on PR #${prNumber} via method: ${mergeMethod}`, {
      component: "GitHubApi",
      isApiCall: true
    });
    return this.request(`/repos/${owner}/${repo}/pulls/${prNumber}/merge`, {
      method: "PUT",
      body: JSON.stringify({
        merge_method: mergeMethod,
        commit_title: commitTitle || undefined,
        commit_message: commitMessage || undefined
      })
    });
  }

  async closePR(owner, repo, prNumber, comment = "") {
    window.logger.info(`Executing closure on PR #${prNumber}`, {
      component: "GitHubApi",
      isApiCall: true,
      meta: { comment }
    });
    if (comment) {
      await this.request(`/repos/${owner}/${repo}/issues/${prNumber}/comments`, {
        method: "POST",
        body: JSON.stringify({ body: comment })
      });
    }
    return this.request(`/repos/${owner}/${repo}/pulls/${prNumber}`, {
      method: "PATCH",
      body: JSON.stringify({ state: "closed" })
    });
  }

  async addLabels(owner, repo, prNumber, labels = []) {
    if (!labels.length) return;
    return this.request(`/repos/${owner}/${repo}/issues/${prNumber}/labels`, {
      method: "POST",
      body: JSON.stringify({ labels })
    });
  }
}

window.GitHubApi = GitHubApi;
