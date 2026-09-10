# WordPress MCP Wrapper

`wpdev` — a reusable wrapper for spinning up local WordPress + Elementor projects that AI
agents can drive through MCP.

Each project gets its own Docker stack, WordPress, Elementor, Elementor's own MCP server, an
MCP Adapter bridge exposing twelve WordPress abilities, and generated MCP configuration for
both GitHub Copilot and Claude Code. The base image is built once and shared, so creating a
project takes seconds rather than minutes.

## Where projects go

**Projects are created outside this repository, by design.**

The default root is `~/dev/<project-name>`; override it with `--root`. This repo is the
wrapper, not a project. `wpdev new` refuses to create a project inside the kit, because such a
project would be committed along with the wrapper.

## Requirements

- Docker with the Compose plugin
- Node.js 22+ (the WordPress MCP proxy recommends it)
- Bash

## Install

```bash
git clone https://github.com/paws1234/WordpressMcpWrapper.git
cd WordpressMcpWrapper

node scripts/fetch-packages.mjs             # download WP-CLI, Elementor, mcp-adapter, theme
./bin/wpdev image                           # build the shared wp-dev image (once)
ln -s "$PWD/bin/wpdev" ~/.local/bin/wpdev   # optional: put wpdev on PATH
./bin/wpdev doctor                          # check the toolchain
./bin/wpdev install-skills                  # /new-project, /plan and two skills
```

`cache/` is gitignored — the packages are third-party binaries and are re-downloaded by
`fetch-packages.mjs`. Fetch them before building the image, or the build has nothing to copy.

## Quick start

```bash
wpdev new my-site        # provisions ~/dev/my-site and starts it
cd ~/dev/my-site
wpdev smoke              # 10 checks, end to end through the real MCP endpoint
```

Then open the project folder in VS Code and start the MCP servers from the MCP view. Start a
**new** session before asking for layouts or content: MCP configuration is read when a session
starts, so the session that created the project cannot reach the servers it just created.

## Layout

```
bin/wpdev            the CLI: new, up, down, wp, shell, creds, smoke, sync, add, doctor
image/Dockerfile     the shared base image (deliberately has no Composer — see docs/SETUP.md)
template/            per-project files: compose file, theme stub, CLAUDE.md, plan.md
scripts/             provisioning, smoke test, package fetching
shared/plugins/      wp-agent-bridge, mounted into every project so one edit updates all
skills/              /new-project, /plan, wordpress-best-practices, visual-testing
docs/                SETUP.md (full walkthrough), PROMPTS.md (what to ask for)
cache/               gitignored downloads
```

Files that `wpdev` copies or generates — everything under `template/`, `skills/` and `docs/` —
use a `__KIT__` placeholder rather than a fixed path. `wpdev` substitutes the real location of
your checkout as it renders them, so the wrapper works from any clone directory.

## Documentation

- [`docs/SETUP.md`](docs/SETUP.md) — architecture, daily commands, and the MCP wiring for both
  clients, including why `.mcp.json` and `.vscode/mcp.json` differ
- [`docs/PROMPTS.md`](docs/PROMPTS.md) — example prompts and the abilities they exercise
- `wpdev help` — every command
