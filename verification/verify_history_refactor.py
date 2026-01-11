import pytest
from unittest.mock import MagicMock, patch, mock_open
import sys
import os

# Add the includes directory to the python path for any potential python processing if needed,
# but here we are primarily verifying file existence and content since we can't run PHP.

def test_files_exist():
    assert os.path.exists("ai-post-scheduler/includes/class-aips-history-controller.php")
    assert os.path.exists("ai-post-scheduler/includes/class-aips-history.php")

def test_controller_content():
    with open("ai-post-scheduler/includes/class-aips-history-controller.php", "r") as f:
        content = f.read()
        assert "class AIPS_History_Controller" in content
        assert "add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));" in content
        assert "public function ajax_clear_history()" in content
        assert "new AIPS_History_Repository()" in content

def test_service_content():
    with open("ai-post-scheduler/includes/class-aips-history.php", "r") as f:
        content = f.read()
        assert "class AIPS_History" in content
        # Ensure AJAX hooks are removed
        assert "add_action('wp_ajax_aips_clear_history'" not in content
        assert "public function ajax_clear_history()" not in content
        # Ensure facade methods exist
        assert "public function get_history" in content
        assert "public function get_stats" in content

def test_main_plugin_file():
    with open("ai-post-scheduler/ai-post-scheduler.php", "r") as f:
        content = f.read()
        assert "require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-controller.php';" in content
        assert "new AIPS_History_Controller();" in content
