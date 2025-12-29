import datetime
import time

def calculate_next_timestamp(freq, base_ts):
    # Simulating PHP strtotime('+1 hour')
    base_dt = datetime.datetime.fromtimestamp(base_ts)
    if freq == 'hourly':
        return (base_dt + datetime.timedelta(hours=1)).timestamp()
    return base_dt.timestamp()

def calculate_next_run_fixed(start_time_str):
    # This mirrors the PHP fix I wrote
    start_dt = datetime.datetime.strptime(start_time_str, '%Y-%m-%d %H:%M:%S')
    base_ts = start_dt.timestamp()
    now_ts = time.time()

    # Catch-up logic
    if base_ts < now_ts:
        limit = 100
        while base_ts <= now_ts and limit > 0:
            base_ts = calculate_next_timestamp('hourly', base_ts)
            limit -= 1
        if limit == 0:
             base_ts = calculate_next_timestamp('hourly', now_ts)

    return datetime.datetime.fromtimestamp(base_ts)

# Test
now = datetime.datetime.now()
start = now - datetime.timedelta(hours=5, minutes=30)
# Ensure seconds are preserved too
start_str = start.strftime('%Y-%m-%d %H:%M:%S')
print(f"Start: {start_str}")
print(f"Now:   {now.strftime('%Y-%m-%d %H:%M:%S')}")

next_run = calculate_next_run_fixed(start_str)
print(f"Next:  {next_run.strftime('%Y-%m-%d %H:%M:%S')}")

# Verify phase (minute and second match)
if start.minute == next_run.minute and start.second == next_run.second:
    print("SUCCESS: Phase preserved")
else:
    print(f"FAILURE: Phase lost. Expected {start.minute}:{start.second}, Got {next_run.minute}:{next_run.second}")

if next_run > now:
    print("SUCCESS: Next run is in future")
else:
    print("FAILURE: Next run is in past")
