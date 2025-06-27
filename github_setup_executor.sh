#!/bin/bash
# 🔗 GitHub Connection Setup - STAYArta Enterprise
# Automated setup for GitHub integration

echo "🚀 Executing GitHub Integration Manager..."

# Execute Python GitHub integration manager
python3 /Users/carlos/Documents/STAYArta_Command_Center/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Active_Scripts/github_integration_manager.py

echo "✅ GitHub Integration Manager completed"

# Make git automation script executable
chmod +x /Users/carlos/Documents/STAYArta_Command_Center/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Active_Scripts/git_integration_automation.sh

# Execute git automation to create initial reports
echo "📊 Running initial Git automation..."
/Users/carlos/Documents/STAYArta_Command_Center/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Active_Scripts/git_integration_automation.sh

echo "🎯 GitHub setup preparation completed successfully"
echo "📋 Check github_setup_instructions.json for next steps"
