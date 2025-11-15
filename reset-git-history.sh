#!/bin/bash
#
# Script to reset git history while preserving all code
#
# WARNING: This will rewrite git history and requires force push!
# Make sure you have a backup and coordinate with your team before running this.
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Git History Reset Script ===${NC}"
echo ""
echo "This script will:"
echo "1. Create a new orphan branch with clean history"
echo "2. Commit all current files as an initial commit"
echo "3. Replace the current branch with the clean history"
echo "4. Require a force push to update the remote"
echo ""

# Get current branch name
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo -e "Current branch: ${GREEN}${CURRENT_BRANCH}${NC}"
echo ""

# Confirm action
read -p "Are you sure you want to reset the git history? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo -e "${RED}Aborted.${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 1: Creating orphan branch...${NC}"
git checkout --orphan temp-reset-history

echo -e "${YELLOW}Step 2: Staging all files...${NC}"
git add -A

echo -e "${YELLOW}Step 3: Creating initial commit...${NC}"
git commit -m "Initial commit - Clean history with all code preserved"

echo -e "${YELLOW}Step 4: Replacing old branch...${NC}"
git branch -D "$CURRENT_BRANCH"
git branch -m "$CURRENT_BRANCH"

echo ""
echo -e "${GREEN}✓ Local git history has been reset!${NC}"
echo ""
echo "To push the changes to remote, run:"
echo -e "  ${YELLOW}git push -f origin ${CURRENT_BRANCH}${NC}"
echo ""
echo -e "${RED}WARNING: Force push will overwrite remote history!${NC}"
echo "Make sure this is what you want before running the push command."
