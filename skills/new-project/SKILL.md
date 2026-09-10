---
name: new-project
description: 'Scaffold a new local WordPress + Elementor project with the wpdev kit — containers, WordPress, Elementor, the MCP Adapter, a project theme and generated MCP config. Use when asked to create, scaffold or set up a new WordPress site, project or sandbox that needs its own directory and containers. Not for adding content or layouts to a project you are already in.'
argument-hint: '<project-name> [--title "Site Title"] [--port 8890]'
disable-model-invocation: true
# Claude Code only, and ignored by VS Code: a pre-approval (not a restriction) so running
# wpdev does not stop to ask for permission. Every other tool stays available as usual.
allowed-tools: Bash
---

# Create a new local WordPress project

## When to use

- The user wants a new WordPress site, project or sandbox to work in
- Work needs its own project rather than the one already open

## Arguments

The project name and flags are whatever was typed after the invocation, for example:

```
/new-project northwind
/new-project northwind --title "Northwind Studio" --port 8890
```

If no project name was given, ask for one and stop. Do not invent one.

## Steps

1. Run the scaffold, passing the arguments through unchanged so `--title` and `--port` keep
   working. Use the absolute path: `wpdev` is not on the PATH of a non-login shell.

   ```bash
   ~/wp-kit/bin/wpdev new <project-name> [flags]
   ```

   This takes a minute or two. It starts the containers, installs WordPress, Elementor and the
   MCP Adapter, provisions the project theme and the shared plugin, enables pretty permalinks,
   and generates the MCP credentials.

2. Report back briefly: the project directory, the site URL and its port, and that the admin
   credentials are in `.env`.

3. Tell the user to start a **new** session in the new project directory before asking for
   layouts or content. MCP configuration is read when a session starts, scoped to the directory
   it was opened in, so the current session cannot reach the servers it just created. Both
   servers need approving once, on first use.

## Do not

- Create project directories by hand. Only the project theme, `uploads` and the kit's plugin
  are mounted, so a hand-made `wp-content/plugins/<slug>/` is invisible to the container and
  fails silently. `wpdev new` gets this right.
- Edit `.env`. `PROJECT_NAME` names the Docker volumes; changing it points the site at an
  empty database.
- Retry blindly on failure. Show the actual error.
