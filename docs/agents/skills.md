# Agent skills in this repo

Matt Pocock's skills (`mattpocock/skills`) are **vendored in git**, not only installed per machine through the plugin.

## Layout

| Path                     | Purpose                                                          |
| ------------------------ | ---------------------------------------------------------------- |
| `skills-lock.json`       | Pins upstream repo, skill path, and content hash for every skill |
| `.agents/skills/<name>/` | Canonical skill tree (commit this)                               |
| `.claude/skills/<name>`  | Symlink → `../../.agents/skills/<name>` (commit this)            |

Do not gitignore `.agents/` or `.claude/skills/`. `.gitignore` ignores the rest of `.claude/` (for example `settings.json`) but re-includes `.claude/skills/`. Do not keep skill trees only under `.claude/skills/` without `.agents/skills/`.

## Add or refresh a skill

1. `npx skills add mattpocock/skills -a claude-code -y --copy -s <skill>` (pass several names after `-s` to add or refresh more than one). This writes `skills-lock.json` and copies the tree to `.claude/skills/<skill>/`.
2. Move the tree to `.agents/skills/<skill>/` and replace it with a symlink:
   `rm -rf .agents/skills/<skill> && mv .claude/skills/<skill> .agents/skills/<skill> && ln -s ../../.agents/skills/<skill> .claude/skills/<skill>`
3. Re-apply the local patch (below).
4. Commit **`.agents/skills/<skill>`**, **`.claude/skills/<skill>`**, and **`skills-lock.json`** in the **same PR**.

## Restore from lock only

```sh
npx skills experimental_install
```

Requires network. Writes each locked skill at the pinned hash; check that the trees land in `.agents/skills/` with `.claude/skills/` symlinks, then re-apply the local patch.

## Local patch: agent-invocable skills

Many upstream skills ship with `disable-model-invocation: true`, which stops agents from starting them through the Skill tool. This repo removes that line from every vendored SKILL.md frontmatter so agents can run the whole flow (`/grill-with-docs`, `/implement`, `/to-spec`, `/to-tickets`, `/triage`, and the rest).

**Exception: `prototype`.** `/prototype` needs Nick's approval before it starts, so `.agents/skills/prototype/SKILL.md` must carry `disable-model-invocation: true` (add it if upstream lacks it).

After any `npx skills add` or `experimental_install`, delete the line from every SKILL.md except `prototype`, and make sure `prototype` has it. `tests/test-skills-vendor.php` (run by `npm test`) fails if any other skill carries the flag, if `prototype` lacks it, if `skills-lock.json` and `.agents/skills/` disagree, or if a `.claude/skills/` symlink is missing or wrong.
