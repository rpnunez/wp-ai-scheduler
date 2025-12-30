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

        now = current_time_timestamp()

        # New Logic
        next_ts = calculate_next_timestamp(frequency, base_time)

        iterations = 0
        max_iterations = 100

        while next_ts <= now and iterations < max_iterations:
            next_ts = calculate_next_timestamp(frequency, next_ts)
            iterations += 1

        if next_ts <= now:
            next_ts = calculate_next_timestamp(frequency, now)

        return datetime.fromtimestamp(next_ts).strftime('%Y-%m-%d %H:%M:%S')

# Test Setup
calculator = AIPS_Interval_Calculator()

schedule_next_run = '2023-10-27 10:00:00'
print(f"Scheduled Run: {schedule_next_run}")
print(f"Actual Execution Time: {datetime.fromtimestamp(mock_now_timestamp)}")

# Emulate fixed implementation (passing argument)
next_run = calculator.calculate_next_run('hourly', schedule_next_run)
print(f"Next Run (Fixed Implementation): {next_run}")

expected_next_run = '2023-10-27 11:00:00'

if next_run != expected_next_run:
    print(f"\n[FAIL] Drift detected! Expected {expected_next_run} but got {next_run}")
else:
    print(f"\n[PASS] No drift. Next run is {next_run}")
