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
                
                // Validate date format
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) {
                    console.warn('Invalid date format:', dateStr);
                    return;
                }
                
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
        calendarContainer.style.display = 'grid';
        calendarContainer.style.gridTemplateColumns = 'repeat(7, 1fr)';
        calendarContainer.style.gap = '4px';
        calendarContainer.style.overflow = 'hidden';

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
        // Check if it's a demo session
        if (String(sessionId).startsWith('demo-')) {
            showSessionModal(sessionId);
            return;
        }
        
        // For real sessions, try clicking the view button
        const sessionCard = document.querySelector(`[data-session-id="${sessionId}"]`);
        if (sessionCard) {
            const viewBtn = sessionCard.querySelector('[data-action="view-session"]');
            if (viewBtn) {
                viewBtn.click();
            }
        }
    }
    
    /**
     * Show session details in a modal for demo sessions
     */
    function showSessionModal(sessionId) {
        // Find session data
        const session = sessionsData.find(s => s.id === sessionId);
        if (!session) {
            console.warn('Session not found:', sessionId);
            return;
        }
        
        // Create modal if it doesn't exist
        let modal = document.getElementById('sessionDetailModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'sessionDetailModal';
            modal.className = 'session-modal active';
            modal.innerHTML = `
                <div class="modal-overlay" onclick="closeSessionModal()"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-calendar-check"></i> Session Details</h3>
                        <button class="modal-close" onclick="closeSessionModal()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body" id="sessionModalBody">
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closeSessionModal()"><i class="fas fa-times"></i> Close</button>
                    </div>
                </div>
            `;
            // Add modal styles
            const style = document.createElement('style');
            style.textContent = `
                .session-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; }
                .session-modal.active { display: flex; }
                .session-modal .modal-content { background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; max-width: 500px; width: 90%; }
                .session-modal .modal-header { padding: 20px; border-bottom: 1px solid var(--border, #2D2D3F); display: flex; justify-content: space-between; align-items: center; }
                .session-modal .modal-header h3 { margin: 0; font-size: 18px; color: var(--text-white, #fff); }
                .session-modal .modal-header i { color: var(--neon, #6B46C1); margin-right: 10px; }
                .session-modal .modal-close { background: none; border: 1px solid var(--border, #2D2D3F); width: 36px; height: 36px; border-radius: 6px; color: var(--text-white, #fff); cursor: pointer; }
                .session-modal .modal-close:hover { background: var(--neon, #6B46C1); border-color: var(--neon, #6B46C1); }
                .session-modal .modal-body { padding: 20px; }
                .session-modal .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border, #2D2D3F); display: flex; justify-content: flex-end; }
                .session-detail-grid { display: flex; flex-direction: column; gap: 16px; }
                .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-main, #0A0A0F); border-radius: 8px; }
                .detail-label { font-size: 14px; color: var(--text-dim, #9CA3AF); display: flex; align-items: center; gap: 8px; }
                .detail-label i { color: var(--neon, #6B46C1); }
                .detail-value { font-size: 14px; font-weight: 600; color: var(--text-white, #fff); }
                .demo-notice { margin-top: 20px; padding: 12px 16px; background: rgba(107, 70, 193, 0.1); border: 1px solid rgba(107, 70, 193, 0.3); border-radius: 8px; font-size: 13px; color: var(--primary-light, #8B5CF6); display: flex; align-items: center; gap: 10px; }
            `;
            document.head.appendChild(style);
            document.body.appendChild(modal);
        } else {
            modal.classList.add('active');
        }
        
        // Update modal content
        const modalBody = document.getElementById('sessionModalBody');
        const dateStr = session.date instanceof Date ? 
            session.date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) :
            'Date TBD';
            
        modalBody.innerHTML = `
            <div class="session-detail-grid">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-hockey-puck"></i> Session Type</span>
                    <span class="detail-value">${session.title || 'Training Session'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Date</span>
                    <span class="detail-value">${dateStr}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-clock"></i> Time</span>
                    <span class="detail-value">${session.time || 'TBD'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Coach</span>
                    <span class="detail-value">${session.coach || 'TBD'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                    <span class="detail-value">${session.location || 'Main Arena'}</span>
                </div>
            </div>
            <div class="demo-notice">
                <i class="fas fa-info-circle"></i> This is demo data. Book real sessions to see actual details.
            </div>
        `;
    }
    
    // Make closeSessionModal available globally
    window.closeSessionModal = function() {
        const modal = document.getElementById('sessionDetailModal');
        if (modal) {
            modal.classList.remove('active');
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCalendar);
    } else {
        initCalendar();
    }

})();
