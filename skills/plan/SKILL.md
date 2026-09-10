---
name: plan
description: 'Execute a written plan step by step, confirming it first. Reads the plan from a file named in the request, otherwise from .claude/plan.md, and otherwise accepts one pasted into the chat. Use when asked to run, execute or follow a plan, to work through plan.md, or to implement a written multi-step spec.'
argument-hint: '[path/to/plan.md — defaults to .claude/plan.md]'
disable-model-invocation: true
---

# Execute a written plan

Do not invent a plan, and do not start work until you have one.

## 1. Find the plan

1. If a file path was typed after the invocation, read that file.
2. Otherwise read `.claude/plan.md`.
3. If neither yields a plan — the file is missing, or its content is only comments and
   whitespace — the plan has not been written yet. Ask the user to either paste the plan into
   the chat, or save it to `.claude/plan.md` and give the path. Then stop and wait for them.

## 2. Confirm before changing anything

- Read `CLAUDE.md` if you have not already this session.
- Restate the plan as a short numbered checklist, then **wait for the user to confirm**. Do not
  edit, create or delete anything until they say go. Running a plan is a deliberate request for
  a multi-step change, and one confirmation is cheap insurance against executing a plan they
  did not intend.

## 3. Then execute it

- Work through the plan in order, one step at a time.
- Follow the project's rules: the `wordpress-best-practices` skill before writing PHP, and the
  `visual-testing` skill whenever appearance matters. Use the MCP servers rather than guessing
  at Elementor data or WordPress state.
- Verify as you go. `__KIT__/bin/wpdev smoke` for anything touching the plugin or the theme.
  Report evidence — command output, ids, screenshots — not assurances.
- If a step is wrong, ambiguous, or turns out to be impossible, stop and say so. Do not quietly
  substitute a different plan.
- Tick off completed steps in the plan file if it uses checkboxes, but do not otherwise rewrite
  the user's plan.

## 4. Finish with

What you changed, what you verified, and what is still undone.
