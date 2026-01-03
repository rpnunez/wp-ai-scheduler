from playwright.sync_api import sync_playwright
import os
import datetime

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        cwd = os.getcwd()
        page.goto(f"file://{cwd}/verification/mock_calendar.html")

        # Inject Date overrides to match the mock events (Jan 2024)
        # We need to reach into the closure or just update the current month?
        # admin-schedule.js exposes `window.AIPS`. But the state `calendarState` is private in the closure.
        # However, the events in the mock HTML are hardcoded to Jan 2024.
        # If today is not Jan 2024, they won't show.
        # I should have mocked the events to be dynamic based on "today".

        # Reload page with dynamic mock data injection via evaluate
        page.reload()

        # Calculate current month dates
        now = datetime.datetime.now()
        current_month_str = now.strftime("%Y-%m")
        post_date = f"{current_month_str}-05 09:00:00"
        sched_date = f"{current_month_str}-10 09:00:00"

        # Redefine $.ajax in page context to use dynamic dates
        page.evaluate(f"""
            $.ajax = function(options) {{
                if (options.data.action === 'aips_get_calendar_events') {{
                    options.success({{
                        success: true,
                        data: {{
                            events: [
                                {{
                                    id: 'post-1',
                                    title: 'Published Post',
                                    start: '{post_date}',
                                    status: 'publish',
                                    color: '#00a32a',
                                    type: 'post'
                                }},
                                {{
                                    id: 'schedule-1',
                                    title: 'Projected Run',
                                    start: '{sched_date}',
                                    status: 'scheduled',
                                    color: '#dcdcde',
                                    type: 'schedule'
                                }}
                            ]
                        }}
                    }});
                }}
            }};
        """)

        # Trigger Render
        # Since initCalendar runs on ready, it might have already run with old ajax.
        # But renderCalendar is called on ready.
        # The ajax call happens.

        # Let's refresh the view manually or reload page?
        # Actually, if I update $.ajax via evaluate BEFORE page load it would be better, but tricky with playwright load.
        # Better: Click "Calendar" button to re-trigger switchView -> renderCalendar.

        page.click('button[data-view="calendar"]')
        page.wait_for_selector('.aips-event')

        # Check events
        events = page.query_selector_all('.aips-event')
        print(f"Found {len(events)} events.")

        titles = [e.inner_text() for e in events]
        if "Published Post" in titles and "Projected Run" in titles:
             print("PASS: Events rendered correctly.")
        else:
             print(f"FAIL: Titles found: {titles}")

        # Check Grid Structure
        days = page.query_selector_all('.aips-calendar-day')
        # Should be roughly 28-31 days plus padding.
        if len(days) >= 28:
            print("PASS: Grid rendered.")
        else:
            print(f"FAIL: Grid has {len(days)} cells.")

        browser.close()

if __name__ == '__main__':
    run()
