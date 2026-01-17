import re
import json

def resolve_ai_variables(text):
    # Simulate PHP preg_match_all logic with normalization
    pattern = r'\{#\s*([\w\s-]+)\s*#\}'

    matches = re.findall(pattern, text)
    full_matches = [f"{{#{m}#}}" for m in matches] # Rough approximation for testing

    # In PHP: preg_match_all gives full matches (Group 0) and captures (Group 1)
    # Python re.findall returns captures if group exists.
    # Let's use finditer for full match access

    iterator = re.finditer(pattern, text)
    vars_map = {}
    unique_normalized_keys = []

    for match in iterator:
        full_match = match.group(0)
        inner = match.group(1).strip()
        normalized_key = f"{{#{inner}#}}"
        vars_map[full_match] = normalized_key
        unique_normalized_keys.append(normalized_key)

    unique_normalized_keys = list(set(unique_normalized_keys))

    return unique_normalized_keys, vars_map

def test_normalization():
    print("Testing Normalization...")

    text = "Compare {# A #} vs {#A#} and {# B #}."
    expected_unique = ["{#A#}", "{#B#}"]

    unique, vars_map = resolve_ai_variables(text)

    print(f"Unique Keys: {unique}")
    print(f"Map: {vars_map}")

    if set(unique) == set(expected_unique):
        print("PASS: Unique keys normalized correctly.")
    else:
        print(f"FAIL: Expected {expected_unique}, got {unique}")

    if vars_map["{# A #}"] == "{#A#}" and vars_map["{#A#}"] == "{#A#}":
        print("PASS: Mapping correct.")
    else:
        print("FAIL: Mapping incorrect.")

def test_substitution_map():
    print("\nTesting Substitution with Map...")

    text = "Compare {# A #} vs {#A#}."
    vars_map = {
        "{# A #}": "{#A#}",
        "{#A#}": "{#A#}"
    }

    # AI returns:
    ai_resolved = {
        "{#A#}": "Apple"
    }

    final_replacements = {}
    for original, normalized in vars_map.items():
        if normalized in ai_resolved:
            final_replacements[original] = ai_resolved[normalized]

    print(f"Final Replacements: {final_replacements}")

    for orig, val in final_replacements.items():
        text = text.replace(orig, val)

    expected = "Compare Apple vs Apple."

    if text == expected:
        print(f"PASS: '{text}' matches expected.")
    else:
        print(f"FAIL: '{text}' != '{expected}'")

if __name__ == "__main__":
    test_normalization()
    test_substitution_map()
