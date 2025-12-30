from datetime import datetime, timedelta

# Mock Environment
mock_now_timestamp = datetime.strptime('2023-10-27 10:05:00', '%Y-%m-%d %H:%M:%S').timestamp()

def current_time_timestamp():
    return mock_now_timestamp

def calculate_next_timestamp(frequency, base_time_ts):
    base_dt = datetime.fromtimestamp(base_time_ts)
    if frequency == 'hourly':
        return (base_dt + timedelta(hours=1)).timestamp()
    return (base_dt + timedelta(days=1)).timestamp()

class AIPS_Interval_Calculator:
    def calculate_next_run(self, frequency, start_time_str=None):
        if start_time_str:
            base_time = datetime.strptime(start_time_str, '%Y-%m-%d %H:%M:%S').timestamp()
        else:
            base_time = current_time_timestamp()

        # THE BUGGY LOGIC mimic
        # if ($base_time < current_time('timestamp')) { $base_time = current_time('timestamp'); }
        if base_time < current_time_timestamp():
            base_time = current_time_timestamp()

        next_ts = calculate_next_timestamp(frequency, base_time)
        return datetime.fromtimestamp(next_ts).strftime('%Y-%m-%d %H:%M:%S')

# Test Setup
calculator = AIPS_Interval_Calculator()

schedule_next_run = '2023-10-27 10:00:00'
print(f"Scheduled Run: {schedule_next_run}")
print(f"Actual Execution Time: {datetime.fromtimestamp(mock_now_timestamp)}")

# Emulate current implementation (no argument)
next_run_current = calculator.calculate_next_run('hourly')
print(f"Next Run (Current Implementation): {next_run_current}")

# Emulate passing argument but with buggy logic
next_run_with_arg = calculator.calculate_next_run('hourly', schedule_next_run)
print(f"Next Run (With Argument, buggy): {next_run_with_arg}")

expected_next_run = '2023-10-27 11:00:00'

if next_run_current != expected_next_run:
    print(f"\n[FAIL] Drift detected! Expected {expected_next_run} but got {next_run_current}")
else:
    print("\n[PASS] No drift.")
