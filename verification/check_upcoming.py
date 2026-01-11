import os

def test_get_upcoming_exists():
    with open("ai-post-scheduler/includes/class-aips-schedule-repository.php", "r") as f:
        content = f.read()
        if "public function get_upcoming" in content:
            print("get_upcoming exists")
        else:
            print("get_upcoming MISSING")
            exit(1)

if __name__ == "__main__":
    test_get_upcoming_exists()
