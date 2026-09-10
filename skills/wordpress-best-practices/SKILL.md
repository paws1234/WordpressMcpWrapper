---
name: wordpress-best-practices
description: 'Engineering standards and discipline for this WordPress project: YAGNI, WordPress coding standards, security (sanitising, escaping, nonces, capabilities), and evidence-based verification instead of assuming a change works. Use when writing or reviewing PHP in the theme or a project plugin, when registering post types, taxonomies, meta, REST routes or shortcodes, when tempted to add abstraction or a settings screen nobody asked for, and before claiming any change is done.'
---

# WordPress best practices

## When to use

- Writing or reviewing PHP in the theme or a project plugin
- Registering post types, taxonomies, meta, REST routes, cron events, or shortcodes
- Deciding whether to build something now or later
- Before reporting that a change works

## YAGNI — the default answer is no

Build only what the request needs.

- No wrapper classes, service containers, or dependency injection. This is a site, not a framework.
- No settings screens, options pages, or admin UI nobody asked for.
- No filters or hooks exposed for a second use case that does not exist yet.
- No extracting two similar functions into an abstraction until a third appears.
- No renaming, reformatting, or restructuring working code as a side effect of an unrelated change.
- No new dependency without saying so out loud and explaining why core cannot do it.

If a request looks like it needs infrastructure, say so and ask. Do not build the
infrastructure first and then look for a use.

## Standards

- WordPress Coding Standards: tabs, Yoda conditions, `snake_case` functions, a docblock on
  every function and class, and translatable strings via `__()` / `esc_html__()` with the
  project's text domain.
- Prefix everything global — functions, classes, hooks, option keys, meta keys.
- One concern per file. The theme holds presentation; the plugin holds behaviour. Post types,
  fields, and shortcodes never go in the theme, because they must survive a theme switch.
- Register hooks inside an `init()` that the bootstrap calls, not at file scope.
- Use `wp_register_ability()` for anything an agent should be able to call, with a real
  `permission_callback` and `meta.public => true`.

## Security — non-negotiable

- Escape on output, not on input: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- Sanitise everything arriving from `$_POST`, `$_GET`, or a REST request:
  `sanitize_text_field()`, `absint()`, `sanitize_key()`.
- Check capability before acting, and use a nonce for anything that mutates state from a form.
- Never interpolate into SQL. Use `$wpdb->prepare()`.
- Never trust a slug, id, path, or URL from a request.
- Do not widen a permission check to make something work. Fix the call, not the guard.

## Evidence over assertion

Work in this order, and do not skip the final step.

1. State the behaviour you are trying to produce, or reproduce the failure.
2. Make the smallest change that could produce it.
3. Run it. In this project that means `~/wp-kit/bin/wpdev smoke` for anything touching the
   plugin or theme, `~/wp-kit/bin/wpdev wp <args>` to inspect state, and the
   `visual-testing` skill when appearance matters.
4. Report the evidence — command output, returned id, screenshot path — not an assertion.
   "Should work" is not a result, and neither is "done" without having looked.
5. If something cannot be verified here, say which part is untested and why.

## Mistakes already made in this project

- Creating `wp-content/plugins/<name>/` by hand. The container never sees it, and nothing
  errors. Use `~/wp-kit/bin/wpdev add plugin <name>`.
- Writing `_elementor_data` with `update_post_meta()`. Generated CSS goes stale. Use the MCP
  tools or the abilities.
- Reading or modifying Elementor's generated CSS files.
- Changing `PROJECT_NAME` in `.env`, which silently repoints the site at an empty database.
- Assuming `wp db check` works. The `wordpress:` image has no `mysql` client; use a command
  that goes through PHP.
