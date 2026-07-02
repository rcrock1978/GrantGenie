# GrantGenie

This is a **Spec Kit** scaffold using **OpenCode**. No application source code exists yet — only meta config in `.specify/` and `.opencode/`.

## Development flow

All work runs through Spec Kit commands (dot separator):

1. `/speckit.specify <description>` — creates `specs/<NNN>-feature-name/spec.md`
2. `/speckit.plan` — generates plan.md, research.md, data-model.md
3. `/speckit.tasks` — generates tasks.md with phased task breakdown
4. `/speckit.implement` — reads tasks.md and implements step by step

Review gates pause between steps for approval.

## Key directories

- `specs/<NNN>-feature-name/` — feature artifacts (spec, plan, tasks, research, contracts)
- `.specify/memory/constitution.md` — project governance (fill from template before first feature)
- `.specify/templates/` — templates for spec, plan, tasks, checklists
- `.opencode/commands/` — installed command definitions (do not edit directly)

## What to read first

For new features, read `.specify/memory/constitution.md` + the relevant template from `.specify/templates/`. The plan.md in each feature directory is the source of truth for tech stack and architecture decisions.

<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan
at /Users/rcrock1978/Documents/PROJECTS/Portfolio_019/GrantGenie/specs/001-grantgenie-core/plan.md
<!-- SPECKIT END -->
