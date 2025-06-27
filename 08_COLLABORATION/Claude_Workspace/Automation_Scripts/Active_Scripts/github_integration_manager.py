#!/usr/bin/env python3
# 🤖 GitHub Integration Manager - STAYArta Enterprise
# Automated GitHub operations and repository management

import json
import subprocess
import datetime
import os
from pathlib import Path

class GitHubIntegrationManager:
    def __init__(self):
        self.command_center = "/Users/carlos/Documents/STAYArta_Command_Center"
        self.novastaybot = "/Users/carlos/novastaybot"
        self.log_file = f"{self.command_center}/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Logs/github_integration.log"
        
    def log_message(self, message):
        timestamp = datetime.datetime.now().isoformat()
        with open(self.log_file, 'a') as f:
            f.write(f"{timestamp}: {message}\\n")
        print(f"{timestamp}: {message}")
    
    def run_git_command(self, command, repo_path):
        """Execute git command and return result"""
        try:
            result = subprocess.run(
                command, 
                shell=True, 
                cwd=repo_path, 
                capture_output=True, 
                text=True
            )
            return result.returncode == 0, result.stdout, result.stderr
        except Exception as e:
            return False, "", str(e)
    
    def check_repository_status(self, repo_path, repo_name):
        """Check Git repository status"""
        self.log_message(f"🔍 Checking {repo_name} repository status")
        
        # Check if repo has remote
        success, stdout, stderr = self.run_git_command("git remote -v", repo_path)
        has_remote = success and "origin" in stdout
        
        # Check current branch
        success, current_branch, _ = self.run_git_command("git branch --show-current", repo_path)
        
        # Check for uncommitted changes
        success, status_output, _ = self.run_git_command("git status --porcelain", repo_path)
        has_changes = success and len(status_output.strip()) > 0
        
        # Get last commit info
        success, last_commit, _ = self.run_git_command("git log -1 --format='%h %s'", repo_path)
        
        return {
            "name": repo_name,
            "path": repo_path,
            "has_remote": has_remote,
            "current_branch": current_branch.strip() if current_branch else "unknown",
            "has_uncommitted_changes": has_changes,
            "last_commit": last_commit.strip() if last_commit else "none",
            "status": "ready_for_github" if not has_changes else "has_uncommitted_changes"
        }
    
    def prepare_github_setup_instructions(self):
        """Generate instructions for GitHub setup"""
        cc_status = self.check_repository_status(self.command_center, "STAYArta Command Center")
        bot_status = self.check_repository_status(self.novastaybot, "NovaSTAYBot")
        
        instructions = {
            "timestamp": datetime.datetime.now().isoformat(),
            "github_setup_required": True,
            "repositories": [cc_status, bot_status],
            "next_steps": [
                "Create GitHub repositories in STAYNova organization",
                "Add remote origins to local repositories", 
                "Push initial commits to GitHub",
                "Setup GitHub Actions for automated deployment",
                "Configure branch protection rules"
            ],
            "github_commands": {
                "command_center": [
                    "# Navigate to Command Center",
                    f"cd {self.command_center}",
                    "# Add GitHub remote (replace with actual repo URL)",
                    "git remote add origin https://github.com/STAYNova/stayarta-command-center.git",
                    "# Push to GitHub",
                    "git push -u origin main",
                    "# Push all branches",
                    "git push --all origin"
                ],
                "novastaybot": [
                    "# Navigate to NovaSTAYBot",
                    f"cd {self.novastaybot}",
                    "# Add GitHub remote (replace with actual repo URL)", 
                    "git remote add origin https://github.com/STAYNova/novastaybot.git",
                    "# Push to GitHub",
                    "git push -u origin main",
                    "# Push all branches",
                    "git push --all origin"
                ]
            }
        }
        
        # Save instructions to file
        instructions_file = f"{self.command_center}/08_COLLABORATION/Claude_Workspace/github_setup_instructions.json"
        with open(instructions_file, 'w') as f:
            json.dump(instructions, f, indent=2)
        
        self.log_message("📋 GitHub setup instructions generated")
        return instructions
    
    def create_deployment_workflows(self):
        """Create GitHub Actions workflows"""
        workflows_dir = f"{self.command_center}/.github/workflows"
        os.makedirs(workflows_dir, exist_ok=True)
        
        # NovaSTAYBot Railway deployment workflow
        bot_workflow = """name: Deploy NovaSTAYBot to Railway

on:
  push:
    branches: [ main ]
    paths: 
      - 'novastaybot/**'
  pull_request:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: novastaybot/package-lock.json
    
    - name: Install dependencies
      run: |
        cd novastaybot
        npm ci
    
    - name: Run tests
      run: |
        cd novastaybot  
        npm test
    
    - name: Deploy to Railway
      if: github.ref == 'refs/heads/main'
      run: |
        # Railway deployment commands will be added here
        echo "Deploying to Railway..."
"""
        
        with open(f"{workflows_dir}/deploy-bot.yml", 'w') as f:
            f.write(bot_workflow)
        
        self.log_message("🚀 GitHub Actions workflows created")
        
    def generate_enterprise_git_summary(self):
        """Generate comprehensive Git status summary"""
        cc_status = self.check_repository_status(self.command_center, "STAYArta Command Center")
        bot_status = self.check_repository_status(self.novastaybot, "NovaSTAYBot")
        
        summary = {
            "timestamp": datetime.datetime.now().isoformat(),
            "enterprise_git_status": "configured",
            "repositories": [cc_status, bot_status],
            "features_implemented": [
                "Local Git repositories initialized",
                "Branch structure created (main, development, feature branches)",
                "Automated commit system",
                "Backup branch management", 
                "Git status reporting",
                "GitHub integration preparation"
            ],
            "automation_level": "advanced",
            "next_phase": "github_integration_and_deployment"
        }
        
        summary_file = f"{self.command_center}/08_COLLABORATION/Claude_Workspace/enterprise_git_summary.json"
        with open(summary_file, 'w') as f:
            json.dump(summary, f, indent=2)
        
        self.log_message("📊 Enterprise Git summary generated")
        return summary

if __name__ == "__main__":
    manager = GitHubIntegrationManager()
    manager.log_message("🚀 GitHub Integration Manager started")
    
    # Prepare GitHub setup
    instructions = manager.prepare_github_setup_instructions()
    
    # Create deployment workflows
    manager.create_deployment_workflows()
    
    # Generate summary
    summary = manager.generate_enterprise_git_summary()
    
    manager.log_message("✅ GitHub Integration Manager completed successfully")
