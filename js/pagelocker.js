/**
 * Sends locks requests to server and when lock has been acquired starts keepalive loop to keep lock.
 */
export class PageLocker {
    sendPageState() {
        // route is empty when user is not on Page
        const route = window.GravAdmin.config.route;
        if (route) {
            void this.acquireLock();
        }
        else {
            void this.removeLock();
        }
    }
    /**
     * Let the server know a Page has been entered for editing.
     * Repeat every `interval` milliseconds to keep alive.
     *
     * @param number interval The interval to let the server know we're still on the page.
     */
    async acquireLock(lastTimestamp = 0) {
        const data = {
            route: window.GravAdmin.config.route,
            url: window.GravAdmin.config.current_url,
            lastTimestamp,
        };
        let answer;
        try {
            const response = await fetch(window.location.pathname + '/pagelocks:acquireLock', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
            if (response.ok) {
                answer = await response.json();
            }
            else {
                this.notifyUser('acquireLock: Unexpected response from server.', 'error');
                console.log(response);
                return;
            }
        }
        catch (error) {
            this.notifyUser('acquireLock: Unexpected error while accessing the server.', 'error');
            console.log(error);
            return;
        }
        if (answer.isLockAcquired) {
            setTimeout(() => {
                void this.acquireLock(answer.lastTimestamp);
            }, pagelocksConfig.keepAliveInterval * 1000);
        }
        else {
            this.makeInterfaceReadonly();
            this.notifyUser(answer.alert, 'error');
        }
    }
    async removeLock() {
        try {
            const response = await fetch(window.location.pathname + '/pagelocks:removeLock', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            if (!response.ok) {
                this.notifyUser('removeLock: Unexpected response from server.', 'error');
                console.log(response);
                return;
            }
        }
        catch (error) {
            this.notifyUser('removeLock: Unexpected error while accessing the server.', 'error');
            console.log(error);
            return;
        }
    }
    /**
     * Make UI readonly when no lock has been acquired.
     */
    makeInterfaceReadonly() {
        const elements = document.querySelectorAll('form#blueprints .tabs-content, #titlebar-button-delete, #titlebar-save, #admin-mode-toggle');
        elements.forEach((element) => {
            element.classList.add('locked');
        });
    }
    /**
     * Show alert banner in top of page
     */
    notifyUser(message, type) {
        const newMessage = document.createElement('div');
        newMessage.className = `${type} alert pagelocks`;
        const newContent = document.createTextNode(message);
        newMessage.appendChild(newContent);
        const messages = document.getElementById('messages');
        messages === null || messages === void 0 ? void 0 : messages.appendChild(newMessage);
    }
}
// window.GravAdmin is set by Admin only when user has logged in
if (window.GravAdmin) {
    const pageLocker = new PageLocker();
    pageLocker.sendPageState();
}
//# sourceMappingURL=pagelocker.js.map