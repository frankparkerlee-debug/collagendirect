# CollagenDirect Collaboration Guide

## Quick Reference Workflow

### Before You Start Working
**ALWAYS pull the latest changes first:**
```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect
git pull origin main
```

### After Making Changes
1. **Check what changed:**
   ```bash
   git status
   git diff
   ```

2. **Stage your changes:**
   ```bash
   git add .
   ```

3. **Commit with a clear message:**
   ```bash
   git commit -m "Brief description of what you changed"
   ```

4. **Push to GitHub:**
   ```bash
   git push origin main
   ```

5. **Automatic deployment:** Render will automatically deploy your changes in 2-5 minutes

## Collaboration Best Practices

### 1. Pull Before You Start
Always run `git pull origin main` before starting work to get the latest changes.

### 2. Commit Frequently
Make small, focused commits rather than large ones. This makes it easier to track changes and resolve conflicts.

### 3. Write Clear Commit Messages
Good examples:
- ✅ "Fix patient file upload validation"
- ✅ "Add email notification for new orders"
- ✅ "Update admin dashboard styling"

Bad examples:
- ❌ "Fixed stuff"
- ❌ "Updates"
- ❌ "WIP"

### 4. Push Regularly
Don't let changes sit on your machine too long. Push them so your collaborator can see them.

### 5. Communicate
Before working on a major feature, let your collaborator know:
- What files you'll be editing
- What feature you're working on
- When you'll be done

Use GitHub, Slack, or text to coordinate.

## Handling Merge Conflicts

If you see this message when pulling:
```
CONFLICT (content): Merge conflict in <filename>
```

### Steps to Resolve:
1. **Open the conflicting file** - look for markers like:
   ```
   <<<<<<< HEAD
   Your changes
   =======
   Their changes
   >>>>>>> origin/main
   ```

2. **Edit the file** to keep the correct version (or merge both)

3. **Remove the conflict markers** (`<<<<<<<`, `=======`, `>>>>>>>`)

4. **Stage the resolved file:**
   ```bash
   git add <filename>
   ```

5. **Complete the merge:**
   ```bash
   git commit -m "Resolve merge conflict in <filename>"
   ```

6. **Push:**
   ```bash
   git push origin main
   ```

## Preventing Conflicts

### Work on Different Files
Coordinate so you're not editing the same files at the same time.

### Work on Different Features
Split up tasks so you're working on different parts of the application.

### Pull Frequently
Even if you're not starting new work, pull changes regularly to stay in sync.

## Emergency: Undo Changes

### Undo uncommitted changes to a file:
```bash
git checkout -- <filename>
```

### Undo your last commit (but keep changes):
```bash
git reset --soft HEAD~1
```

### Undo your last commit (and discard changes):
```bash
git reset --hard HEAD~1
```

**WARNING:** `--hard` permanently deletes changes!

## Project Information

- **Repository:** https://github.com/mattedesign/collagendirect
- **Live Site:** https://collagendirect-2v96.onrender.com/
- **Render Dashboard:** https://dashboard.render.com/web/srv-d3tav58dl3ps73e9a5ig
- **Branch:** main (auto-deploys to production)

## Deployment Info

- **Auto-deploy:** Enabled on push to main
- **Build time:** ~2-5 minutes
- **Health check:** `/portal/health.php` must respond successfully
- **Logs:** Check Render dashboard if deployment fails

## Quick Troubleshooting

### "Your branch is behind 'origin/main'"
```bash
git pull origin main
```

### "Your branch is ahead of 'origin/main'"
```bash
git push origin main
```

### "Working tree has uncommitted changes"
Either commit them:
```bash
git add .
git commit -m "Your message"
```

Or discard them:
```bash
git reset --hard HEAD
```

### Deployment Failed
1. Check Render dashboard for error logs
2. Check that `/portal/health.php` works
3. Check for PHP syntax errors
4. Check database connection

## Getting Help

- **Git documentation:** https://git-scm.com/doc
- **GitHub guides:** https://guides.github.com/
- **Render docs:** https://render.com/docs

## Contact

If you have questions about the workflow, coordinate with your collaborator before making changes that might conflict.
