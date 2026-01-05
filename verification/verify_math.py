
import math

def calculate_occurrences(frequency, start, end):
    if start > end:
        return 0

    fixed_intervals = {
        'hourly': 3600,
        'every_4_hours': 14400,
        'every_6_hours': 21600,
        'every_12_hours': 43200,
        'daily': 86400,
        'weekly': 604800,
        'bi_weekly': 1209600,
    }

    if frequency in fixed_intervals:
        interval = fixed_intervals[frequency]
        return math.floor((end - start) / interval) + 1

    return 0 # Mock variable

def test_occurrences():
    start = 1000
    end = 1000 + 86400 * 2 # 2 days later

    # Daily: 1000, 1000+86400, 1000+86400*2 = 3 occurrences
    res = calculate_occurrences('daily', start, end)
    print(f"Daily: {res}")
    assert res == 3

    # Hourly: 48 + 1 = 49
    res = calculate_occurrences('hourly', start, end)
    print(f"Hourly: {res}")
    assert res == 49

    # Range smaller than interval
    end_small = start + 3600
    # Daily: 1 (start)
    res = calculate_occurrences('daily', start, end_small)
    print(f"Daily small: {res}")
    assert res == 1

    # Exact match end
    end_exact = start + 86400
    res = calculate_occurrences('daily', start, end_exact)
    print(f"Daily exact: {res}")
    assert res == 2

if __name__ == "__main__":
    test_occurrences()
    print("Tests passed")
