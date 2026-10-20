function createResponsiveCalendarOptions() {
    const smallScreen = window.matchMedia('(max-width: 767px)');
    let mobile = smallScreen.matches;
    return {
        initialView: mobile ? 'listMonth' : 'dayGridMonth',
        buttonText: { today: 'วันนี้', month: 'เดือน', week: 'สัปดาห์', list: 'รายการ' },
        views: {
            listMonth: { buttonText: 'รายการ' }, listWeek: { buttonText: 'รายการ' },
            dayGridMonth: { dayHeaderContent: info => ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'][info.date.getDay()] }
        },
        noEventsContent: 'ไม่มีกิจกรรมในช่วงวันที่นี้',
        fixedWeekCount: false,
        dayMaxEvents: 2,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: mobile ? 'dayGridMonth,listMonth' : 'dayGridMonth,timeGridWeek,listWeek'
        },
        windowResize: info => {
            if (mobile === smallScreen.matches) return;
            mobile = smallScreen.matches;
            const calendar = info.view.calendar;
            calendar.setOption('headerToolbar', {
                left: 'prev,next today', center: 'title',
                right: mobile ? 'dayGridMonth,listMonth' : 'dayGridMonth,timeGridWeek,listWeek'
            });
            calendar.changeView(mobile ? 'listMonth' : 'dayGridMonth');
        }
    };
}
