/**
 * Arctic Wolves Calendar View
 * JavaScript for interactive calendar display of sessions
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Calendar state
    let currentDate = new Date();
    let sessionsData = [];

    /**
     * Initialize calendar functionality
     */
    function initCalendar() {
        // Only run if calendar view is active
        const calendarGrid = document.getElementById('calendarGrid');
        if (!calendarGrid) return;

        // Get session data from PHP
        loadSessionsData();

        // Set up navigation
        const prevBtn = document.getElementById('prevMonth');
        const nextBtn = document.getElementById('nextMonth');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });
        }

        // Initial render
        renderCalendar();
    }

    /**
     * Load sessions data from existing session cards in the page
     */
    function loadSessionsData() {
        // Check for hidden session data first (calendar view)
        const hiddenSessions = document.querySelectorAll('#sessionsData .session-data');
        if (hiddenSessions.length > 0) {
            hiddenSessions.forEach(sessionEl => {
                const dateStr = sessionEl.dataset.date;
                if (!dateStr) return;
                
                const date = new Date(dateStr);
                
                sessionsData.push({
                    id: sessionEl.dataset.sessionId,
                    date: date,
                    title: sessionEl.dataset.title || '',
                    time: sessionEl.dataset.time || '',
                    coach: sessionEl.dataset.coach || '',
                    location: sessionEl.dataset.location || ''
                });
            });
            return;
        }
        
        // Fallback: Get all session cards from the list view to extract data
        const sessionCards = document.querySelectorAll('[data-component="SessionCard"]');
        
        sessionCards.forEach(card => {
            const sessionId = card.dataset.sessionId;
            const dateBox = card.querySelector('.date-box');
            const title = card.querySelector('.session-title')?.textContent || '';
            const meta = card.querySelector('.session-meta');
            
            if (!dateBox) return;
            
            // Parse date from the date box
            const day = dateBox.querySelector('.date-day')?.textContent || '';
            const month = dateBox.querySelector('.date-month')?.textContent || '';
            
            // Parse time from meta
            let time = '';
            const timeMeta = meta?.querySelector('span:first-child')?.textContent || '';
            const timeMatch = timeMeta.match(/(\d{1,2}:\d{2}\s*[AP]M)/i);
            if (timeMatch) {
                time = timeMatch[1];
            }
            
            // Parse coach from meta
            let coach = '';
            const coachMeta = Array.from(meta?.querySelectorAll('span') || []).find(s => 
                s.textContent.includes('fa-user')
            );
            if (coachMeta) {
                coach = coachMeta.textContent.replace(/\s+/g, ' ').trim();
            }
            
            // Create date object
            const monthMap = {
                'JAN': 0, 'FEB': 1, 'MAR': 2, 'APR': 3, 'MAY': 4, 'JUN': 5,
                'JUL': 6, 'AUG': 7, 'SEP': 8, 'OCT': 9, 'NOV': 10, 'DEC': 11
            };
            
            const monthNum = monthMap[month.toUpperCase()];
            if (monthNum === undefined) return;
            
            const year = new Date().getFullYear();
            const date = new Date(year, monthNum, parseInt(day));
            
            sessionsData.push({
                id: sessionId,
                date: date,
                title: title,
                time: time,
                coach: coach
            });
        });
    }

    /**
     * Render the calendar
     */
    function renderCalendar() {
        const calendarGrid = document.getElementById('calendarGrid');
        const currentMonthEl = document.getElementById('currentMonth');
        
        if (!calendarGrid) return;

        // Update month display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        if (currentMonthEl) {
            currentMonthEl.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        }

        // Clear existing content
        calendarGrid.innerHTML = '';

        // Get first day of month and number of days
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Create calendar structure
        const calendarContainer = document.createElement('div');
        calendarContainer.className = 'calendar-container';

        // Add day headers
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            calendarContainer.appendChild(header);
        });

        // Add empty cells for days before month starts
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-day empty';
            calendarContainer.appendChild(emptyCell);
        }

        // Add day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'calendar-day';
            
            const currentDayDate = new Date(year, month, day);
            const isToday = isSameDay(currentDayDate, new Date());
            
            if (isToday) {
                dayCell.classList.add('today');
            }

            // Day number
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayCell.appendChild(dayNumber);

            // Check for sessions on this day
            const daySessions = sessionsData.filter(session => 
                isSameDay(session.date, currentDayDate)
            );

            if (daySessions.length > 0) {
                dayCell.classList.add('has-sessions');
                
                // Add session indicators
                const sessionsContainer = document.createElement('div');
                sessionsContainer.className = 'day-sessions';
                
                daySessions.forEach((session, index) => {
                    if (index < 3) { // Show max 3 sessions per day
                        const sessionEl = document.createElement('div');
                        sessionEl.className = 'session-indicator';
                        sessionEl.textContent = `${session.time} - ${session.title}`;
                        sessionEl.title = `${session.title}\n${session.time}\n${session.coach}`;
                        sessionEl.dataset.sessionId = session.id;
                        
                        // Make clickable
                        sessionEl.addEventListener('click', (e) => {
                            e.stopPropagation();
                            viewSession(session.id);
                        });
                        
                        sessionsContainer.appendChild(sessionEl);
                    }
                });
                
                // Show "+X more" if there are more sessions
                if (daySessions.length > 3) {
                    const moreEl = document.createElement('div');
                    moreEl.className = 'session-indicator more';
                    moreEl.textContent = `+${daySessions.length - 3} more`;
                    sessionsContainer.appendChild(moreEl);
                }
                
                dayCell.appendChild(sessionsContainer);
            }

            calendarContainer.appendChild(dayCell);
        }

        calendarGrid.appendChild(calendarContainer);
    }

    /**
     * Check if two dates are the same day
     */
    function isSameDay(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }

    /**
     * View session details
     */
    function viewSession(sessionId) {
        // This would typically open a modal or navigate to session details
        // For now, we'll use the existing view-session action
        const sessionCard = document.querySelector(`[data-session-id="${sessionId}"]`);
        if (sessionCard) {
            const viewBtn = sessionCard.querySelector('[data-action="view-session"]');
            if (viewBtn) {
                viewBtn.click();
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCalendar);
    } else {
        initCalendar();
    }

})();
