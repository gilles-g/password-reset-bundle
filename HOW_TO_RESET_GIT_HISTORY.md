# How to Reset Git History

This document explains how to reset the git history of this repository while keeping all the code.

## Prerequisites

- Git installed on your machine
- Write access to the repository
- Ability to force push to the branch

## Steps to Reset Git History

### 1. Create a new orphan branch

```bash
git checkout --orphan temp-reset-history
```

This creates a new branch with no commit history.

### 2. Stage all files

```bash
git add -A
```

All files in the working directory will be staged for the initial commit.

### 3. Create the initial commit

```bash
git commit -m "Initial commit - Password Reset Bundle"
```

This creates a single commit with all the current code.

### 4. Delete the old branch

```bash
git branch -D copilot/reset-git-history-keep-code
```

### 5. Rename the temporary branch

```bash
git branch -m copilot/reset-git-history-keep-code
```

### 6. Force push to remote

```bash
git push -f origin copilot/reset-git-history-keep-code
```

⚠️ **Warning**: This will overwrite the remote branch history. Make sure you want to do this!

## Alternative: Reset main branch

If you want to reset the main branch instead:

```bash
git checkout --orphan temp-reset-history
git add -A
git commit -m "Initial commit - Password Reset Bundle"
git branch -D main
git branch -m main
git push -f origin main
```

## What This Does

- Creates a fresh git history with a single initial commit
- Preserves all code, tests, documentation, and configuration files
- Removes all previous commit history
- The repository size may decrease as old history is removed

## When to Use This

- Cleaning up a messy commit history
- Starting fresh while keeping the code
- Removing sensitive data from history (though git filter-branch or BFG Repo-Cleaner are better for this)
- Simplifying repository for new contributors

## Important Notes

- **Backup first**: Make sure you have a backup of the repository before resetting history
- **Coordinate with team**: If others are working on the repository, coordinate with them first
- **Protected branches**: You may need to temporarily disable branch protection rules
- **Force push required**: This operation requires force push (`-f` flag), which overwrites remote history
