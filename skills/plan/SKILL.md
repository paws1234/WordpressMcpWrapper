---
name: plan
description: 'Turn a plan file into a task file, then execute it task by task. Reads the plan from a path named in the request, otherwise from .claude/plan.md, and writes a sibling <name>-tasks.md whose tasks each carry their own context, scope limits, acceptance criteria and verification. Use when asked to run, execute or follow a plan, to work through plan.md, to break a plan into tasks, or to implement a written multi-step spec.'
argument-hint: '[docs/<name>-plan.md — defaults to .claude/plan.md]'
disable-model-invocation: true
---

# Execute a written plan

Do not invent a plan, and do not start work until you have one. `/plan` works from a plan
**file**; it never accepts a plan pasted into the chat.

## 1. Find the plan

1. If a file path was typed after the invocation, read that file.
2. Otherwise read `.claude/plan.md`.
3. If there is no plan — the file is missing, or its content is only comments and whitespace —
   do not guess and do not accept a pasted plan. Ask the user to save the plan to a file,
   suggesting `docs/<name>-plan.md`, show them the headings below, then stop and wait.

Those headings exist so the generated tasks can inherit real limits:

- **Goal** — what should be true when this is done
- **Steps** — what to do, in order
- **Constraints / Out of scope** — files and behaviour that must not change
- **Done when** — acceptance criteria

## 2. Write the task file

Split the plan into tasks and write them to a file beside the plan:

| Plan file | Task file |
| --- | --- |
| `docs/pricing-page-plan.md` | `docs/pricing-page-tasks.md` |
| `docs/pricing-page.md` | `docs/pricing-page-tasks.md` |
| `.claude/plan.md` | `.claude/tasks.md` |
| `docs/plan.md` | `docs/tasks.md` |

That is: same directory, strip a trailing `-plan` (or a basename of exactly `plan`), append
`-tasks`.

Writing this file is the one thing you do **before** confirmation — it is what the user
confirms. Nothing else is created, edited or deleted yet. If the task file already exists and
has content, never overwrite it: ask whether to replace it or resume at the first unticked
task, and resume if any are already ticked.

### Rules for the tasks

- One task is one verifiable change, small enough to finish in one session.
- A step that needs more than one change becomes more than one task.
- Every task must be runnable by a fresh session that has this repository and the task file and
  nothing else. Name the files, not the conversation.
- Never write a task whose goal cannot be observed. "Refactor", "polish" and "improve" are not
  tasks until they say what will be different and how that is checked.
- Inherit the plan's constraints and out-of-scope limits. If the plan states none, ask for them
  rather than inventing them.
- Prefer fewer, sharper tasks. If the plan is too large for one sitting, say so; do not quietly
  trim it.

### The shape of a task

Each task is a heading with a checkbox and only these fields:

- `### T3 — <title>` and a `- [ ]` checkbox
- **Goal** — one sentence, one deliverable
- **Depends on** — task ids, or `none`; **Parallel with** — task ids, if any
- **Context to load** — the exact files, symbols or line ranges, the skills to read, the MCP
  server and tool to use, and the output of any earlier task. Nothing more: this is the task's
  context budget
- **Do** — numbered, concrete actions
- **In scope** — the files this task may change; **Out of scope** — what it must not touch: no
  refactors, no new dependencies, nothing unasked for
- **Acceptance criteria** — one to four statements that can be shown true or false, each naming
  the observable outcome
- **Verify** — the exact command or tool call, and the evidence to report:
  `__KIT__/bin/wpdev smoke` for anything touching the plugin or the theme, `php -l`, a WP-CLI
  query, or screenshots through Playwright at desktop, tablet and mobile for anything visual
- **Size** — S, M or L; an L is split before you start

Open the file with the source plan's path, today's date, the task order, a line saying a single
task can be run by asking a session to do `T3` of this file, and a definition of done for the
whole plan.

## 3. Confirm

- Read `CLAUDE.md` if you have not already this session.
- Show the tasks you wrote — ids, titles, files touched, dependencies — then **wait for the
  user to confirm**. Do not execute anything until they say go. Running a plan is a deliberate
  request for a multi-step change, and one confirmation is cheap insurance against implementing
  a plan they did not intend.

## 4. Execute, one task at a time

- Work in order. For the task you are on, load only its **Context to load**, do the work, then
  run its **Verify**.
- Tick that task's checkbox in the task file and add one line under it recording the evidence:
  the command and its observed result. That is the record the next session reads.
- Report briefly, then move to the next unticked task.
- If the user names a single task, resume there instead, once its dependencies are ticked.
- Follow the project's rules: `wordpress-best-practices` before writing PHP, `visual-testing`
  whenever appearance matters, and the MCP servers rather than guessing at Elementor data or
  WordPress state.
- If a task is wrong, ambiguous or turns out to be impossible, stop and say so, and propose the
  correction to the task file. Do not quietly substitute a different plan.
- Never edit the source plan file. Ticking tasks and recording evidence belongs to the task
  file.

## 5. Finish with

What you changed and the evidence for it, which tasks are done and which are not, and anything
you learned that changes the remaining tasks.
