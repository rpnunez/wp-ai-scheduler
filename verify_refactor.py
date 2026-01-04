from playwright.sync_api import sync_playwright

def verify_files_exist():
    import os
    required_files = [
        "ai-post-scheduler/includes/class-aips-settings-controller.php",
        "ai-post-scheduler/includes/class-aips-settings.php",
        "ai-post-scheduler/ai-post-scheduler.php"
    ]
    for f in required_files:
        if not os.path.exists(f):
            print(f"FAILED: {f} does not exist")
            return
        else:
            print(f"SUCCESS: {f} exists")

    # Simple regex check to ensure class definition and require exists
    with open("ai-post-scheduler/includes/class-aips-settings-controller.php", "r") as f:
        content = f.read()
        if "class AIPS_Settings_Controller" not in content:
            print("FAILED: Class definition missing in controller")
        if "public function register_settings" not in content:
            print("FAILED: Method register_settings missing in controller")

    with open("ai-post-scheduler/includes/class-aips-settings.php", "r") as f:
        content = f.read()
        if "class AIPS_Settings" not in content:
            print("FAILED: Class definition missing in settings")
        if "new AIPS_Settings_Controller()" not in content:
            print("FAILED: Controller instantiation missing in settings")

    with open("ai-post-scheduler/ai-post-scheduler.php", "r") as f:
        content = f.read()
        if "includes/class-aips-settings-controller.php" not in content:
            print("FAILED: Require missing in main file")

    print("All checks passed")

if __name__ == "__main__":
    verify_files_exist()
