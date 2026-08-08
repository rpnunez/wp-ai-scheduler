# QA Builds

Bundle N open pull requests onto one branch, then run that branch in an
isolated Docker stack loaded with a copy of production data.

The point is to answer "do these PRs work *together*, against *real* templates,
schedules and history?" — a question neither CI nor a single-PR checkout can
answer.

---

## Quick start

```bash
# One-time: export the DevStackTips database and media, and cache them
make qa-seed PRS=1887,1888 FILE=~/Downloads/devstacktips.sql UPLOADS=~/Downloads/uploads.zip

# Build the bundle and run it
make qa-build PRS=1887,1888
make qa-up    PRS=1887,1888

# -> prints the build's URL, e.g. http://localhost:8137/wp-admin  (admin / admin)
```

`make qa-build` and `make qa-up` can be collapsed — `qa-up` creates the build
if it does not exist yet:

```bash
make qa-up PRS=1887,1888,1885
```

---

## How a build is identified

Everything keys off the **sorted, de-duplicated** PR id list, so `1888,1887`
and `1887,1888` are the same build and never produce a duplicate.

| Thing | Value for `PRS=1888,1887` |
|---|---|
| Build key | `1887-1888` |
| Git branch | `qa-build-1887,1888` |
| Compose project | `qabuild-1887-1888` |
| Worktree | `.qa-builds/1887-1888/src` |
| State file | `.qa-builds/1887-1888/build.env` |

Commas in the branch name show up URL-encoded (`%2C`) on github.com. Set
`QA_ID_SEPARATOR=-` in your environment for `qa-build-1887-1888` instead.

---

## Building the branch

```bash
make qa-build PRS=1887,1888          # branch locally only
make qa-build PRS=1887,1888 PR=1     # also push and open a draft PR
make qa-build PRS=1887,1888 FORCE=1  # rebuild from a fresh main
```

What it does:

1. Fetches `origin/main` and each PR head via `refs/pull/<n>/head`. This needs
   no GitHub CLI and no extra auth — plain `git fetch` is enough.
2. Creates the branch in a **git worktree** under `.qa-builds/`. Your own
   checkout, current branch and staged changes are never touched.
3. Merges each PR in ascending id order with `--no-ff`.
   After merging it runs `composer dump-autoload --no-dev` in the worktree. The
   autoloader files the repo commits are generated *with* dev requirements, so
   `autoload_real.php` requires `myclabs/deep-copy` — a PHPUnit transitive that
   only exists after `composer install`. A fresh worktree has only the committed
   stubs, so without this the plugin fatals on activation. Regenerating needs no
   network, since the plugin has no runtime requirements beyond PHP itself.
4. **A PR that conflicts is aborted and skipped**, and the build continues. One
   bad PR never costs you the whole bundle — the summary tells you which landed:

```
==> Build summary
  branch   qa-build-1885,1887,1888
  merged   1885,1887
  skipped  1888 (merge conflicts)
  commits  9 ahead of main
```

`PR=1` additionally pushes the branch and opens a **draft** PR labelled
`qa-build`, whose body tables every bundled PR with its merge status. It is
marked *not for merge* — it exists so the bundle is reviewable and shareable,
the way PR #1867 was assembled by hand.

`gh` is only needed for `PR=1` and for decorating merge commits with PR titles.
Everything else works without it.

---

## Running a build

Each build gets its own compose project, so its containers, network, database
and WordPress core are separate from the dev stack on 8080 **and** from every
other QA build.

Each build draws a **random free port** from a per-service range, records it in
`build.env`, and keeps it for the life of the build — restarting a build returns
it to the same URL.

| Service | Dev stack | QA build range |
|---|---|---|
| WordPress | 8080 | 8100–8299 |
| phpMyAdmin | 8082 | 8300–8399 |
| MySQL | 3307 | 3400–3499 |
| Xdebug | 9003 | 9100–9199 |

Ports are random rather than sequential so that builds created and torn down
over time do not keep reusing the same few numbers — a stale browser tab or
bookmark pointing at a build you deleted will simply fail to connect instead of
quietly loading a *different* build on the port it inherited.

When you want a stable, memorable URL, pin it:

```bash
make qa-build PRS=1887,1888 PORT=8150     # at creation
make qa-up    PRS=1887,1888 PORT=8150     # or move an existing build
```

Moving an existing build rewrites its stored `siteurl`/`home` and post content
to the new port, so it stays browsable. A port already assigned to another build
or in use on the machine is refused rather than silently reassigned.

So `make up` and several QA builds can all run at once, and you can compare them
side by side in different browser tabs.

```bash
make qa-list                      # every build, its ports, running or not
make qa-logs    PRS=1887,1888
make qa-shell   PRS=1887,1888
make qa-down    PRS=1887,1888     # stop, keep the data and the ports
make qa-down    PRS=1887,1888 PURGE=1   # delete volumes and worktree too
make qa-down    ALL=1
```

---

## Database modes

`make qa-up DB=<mode>` decides what the build's database contains.

| Mode | Effect |
|---|---|
| `seed` | Wipe this build's database, import the cached production dump, rewrite URLs |
| `keep` | Reuse whatever is already in this build's database |
| `fresh` | Vanilla WordPress install, no production data |
| `clone:<key>` | Copy another build's database; `clone:dev` copies the main 8080 stack |

**Auto-seeding only ever happens for a build that has no database yet.** When
`DB=` is omitted:

- database already exists → `keep`
- no database, cached seed exists → `seed`
- no database, no cached seed → `fresh`

So re-running `make qa-up` never silently discards test state you built up, and
a build that does not need production data never pays for importing it. To
deliberately reload:

```bash
make qa-up PRS=1887,1888 DB=seed
```

To point a build at a build you already have configured how you like:

```bash
make qa-up PRS=1890 DB=clone:1887-1888
```

---

## The production seed

Export the DevStackTips database however you prefer — phpMyAdmin, your host's
panel, or `wp db export` — then register the file once:

```bash
make qa-seed PRS=1887,1888 FILE=~/Downloads/devstacktips.sql
```

It is cached at `.qa-builds/_seed/prod.sql` and reused by every later build, so
you only re-export when you want fresher data. `.sql` and `.sql.gz` are both
accepted. Nothing about production credentials is stored, and `.qa-builds/` is
gitignored.

Importing a production dump into a local container normally breaks in four
predictable ways. `qa-seed.sh` repairs each one:

| Problem | What the script does |
|---|---|
| Dump has `CREATE DATABASE` / `USE` and imports into the wrong schema | Strips those statements while caching, and reports how many |
| Dump uses a non-`wp_` table prefix | Detects the prefix from the shipped `*options` table and rewrites `wp-config.php` |
| Every URL still points at production | Reads the dump's own `siteurl`, then `wp search-replace` to this build's port, serialization-safe and skipping `guid` |
| Role/capability keys disagree with the table prefix | Repoints `{prefix}user_roles` and the prefix-derived usermeta keys, which otherwise leaves every user with no role |
| No local password for any production user | Creates or resets an `admin` / `admin` administrator |

It then activates the plugin so `AIPS_DB_Migrations::check_and_run()` executes
against real data — which is often the most valuable thing the whole setup
buys you — and warns about plugins the dump had active that are not installed
locally.

---

## Production media

Imported posts reference media by URL, so without the real `wp-content/uploads`
every image 404s. Register media the same way you register the dump — export it
from production as a directory or archive, then cache it once:

```bash
make qa-seed PRS=1887,1888 UPLOADS=~/Downloads/uploads.zip
```

Directories, `.zip`, `.tar.gz` and `.tar` are all accepted, and the archive may
be rooted at `wp-content/uploads/`, `uploads/`, or the year folders themselves —
all three normalize to the same cached layout at `.qa-builds/_seed/uploads/`.
You are warned if no year folders (`2025/`, `2024/`, …) turn up where expected,
which usually means the archive was rooted somewhere unexpected.

### Media modes

`make qa-up UPLOADS_MODE=<mode>` decides how a build gets that media.

| Mode | Effect |
|---|---|
| `copy` | **Default.** Duplicate the cached media into the build's own uploads volume |
| `mount` | Bind the shared cache in directly — one copy on disk for all builds |
| `skip` | Leave uploads empty |

`copy` is the default because it is the only mode where media a build
*generates* stays with that build. The plugin writes new attachments during
generation; under `copy` those land in the build's own volume, and the cached
production copy stays pristine.

`mount` exists because production media libraries are routinely tens of
gigabytes and copying that per build is real disk. The trade-off is real too:
the mount is read-write, so anything a build writes lands in the shared cache
and is visible to every other build in mount mode.

```bash
make qa-up PRS=1887,1888 UPLOADS_MODE=mount   # large library, share one copy
```

Switching modes on an existing build recreates its web container so the new
mount takes effect. Switching back to `copy` restores the build's own volume
with whatever it had generated — the copy merges over it rather than wiping it.

### Refreshing media alone

If you register media after a build was already seeded, apply it without
touching the database:

```bash
make qa-uploads PRS=1887,1888
make qa-uploads PRS=1887,1888 UPLOADS=~/Downloads/uploads-fresh.zip
```

`make qa-up` also fills in media on its own when a build's uploads directory is
still empty, but it will never overwrite media a build already has.

---

## Relationship to `scripts/import-db-docker.sh`

That script still does what it always did: import a dump into the **main** dev
stack on 8080. `qa-seed.sh` is the per-build equivalent — it targets one QA
build's isolated project, and adds the prefix detection, URL auto-detection and
admin provisioning described above. Neither replaces the other.

---

## Why not an off-the-shelf tool?

The merge half exists in several products — GitHub's native merge queue,
Mergify batches, and the `combine-prs` Action all build a temporary branch from
N pull requests. Every one of them runs the batch **in CI and then discards the
branch**. None gives you a local branch you can point a Docker stack at with
your own production data, which is the entire point here. So the merge half is
a thin wrapper over `git merge`, and the environment half re-parameterizes the
Docker stack that already existed.

---

## Files

| File | Role |
|---|---|
| `scripts/qa-lib.sh` | Shared naming, state, port allocation, compose wrapper |
| `scripts/qa-build.sh` | Branch + merge + conflict report + optional draft PR |
| `scripts/qa-up.sh` | Start a build's stack, resolve the database mode |
| `scripts/qa-seed.sh` | Cache and import the production dump and media, repair it |
| `scripts/qa-down.sh` | Stop or purge a build |
| `scripts/qa-list.sh` | Show all builds |
| `scripts/qa-compose.sh` | Arbitrary compose command against one build |
| `docker-compose.qa.yml` | Overlay: container names, plugin mount from worktree |
| `docker-compose.qa-uploads.yml` | Third overlay, applied only in `mount` media mode |

`docker-compose.yml` reads `WP_PORT`, `MYSQL_PORT`, `PHPMYADMIN_PORT` and
`XDEBUG_PORT` (defaulting to 8080/3307/8082/9003, unchanged) so QA stacks can
claim different ports. Compose *appends* to a service's `ports` list rather
than replacing it, so those had to be variables in the base file — an overlay
alone would publish both.
