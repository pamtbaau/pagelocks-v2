class PageLocksAdmin {
    async readLocks() {
        let answer;
        try {
            const response = await fetch(window.location.pathname + '/pagelocks:readLocks', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            if (response.ok) {
                answer = await response.json();
            }
            else {
                this.notify('ReadLocks: No valid response from server.', 'error');
                return;
            }
        }
        catch (error) {
            this.notify('Readlocks: Unexpected error while accessing the server.', 'error');
            return;
        }
        if (answer) {
            if (Object.keys(answer.locks).length === 0) {
                this.notify(answer.countAlert, 'info');
            }
            this.displayLocks(answer);
        }
    }
    displayLocks(data) {
        const tableRows = document.getElementById('locklist');
        tableRows.innerHTML = '';
        Object.keys(data.locks).forEach((url, i) => {
            const lock = data.locks[url];
            const date = new Date(lock.timestamp * 1000);
            const lockRow = document.createElement('tr');
            lockRow.innerHTML = `
            <td class="author">${lock.email}</td>
            <td class="route">${url}</td>
            <td class="since">${date.toLocaleTimeString()}</td>
            <td id="row${i}" class="delete">
                <a href="#delete" 
                    class="page-delete delete-action" 
                    title="Delete Item"
                    >
                    <i class="fa fa-close"></i>
                </a>
            </td>
            `;
            tableRows.appendChild(lockRow);
            const row = document.getElementById(`row${i}`);
            row.addEventListener('click', () => {
                if (window.confirm(data.alert.replace('%s', lock.fullname))) {
                    void this.removeLock(url);
                }
            });
        });
    }
    async removeLock(url) {
        let response;
        try {
            const data = {
                url,
            };
            response = await fetch(window.location.pathname + '/pagelocks:forceRemoveLock', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
        }
        catch (error) {
            this.notify('RemoveLock: Unexpected error while accessing the server.', 'error');
            return;
        }
        this.clearAlerts();
        if (response.ok) {
            const answer = await response.json();
            if (answer.isLockRemoved) {
                this.notify(answer.alert, 'info');
            }
            else {
                this.notify(answer.alert, 'error');
            }
            void this.readLocks();
        }
        else {
            this.notify('No valid response from server.', 'error');
        }
    }
    notify(message, type) {
        const newMessage = document.createElement('div');
        newMessage.className = `${type} alert pagelocks`;
        const newContent = document.createTextNode(message);
        newMessage.appendChild(newContent);
        const messages = document.getElementById('messages');
        messages === null || messages === void 0 ? void 0 : messages.appendChild(newMessage);
    }
    clearAlerts() {
        const alerts = document.getElementsByClassName('alert pagelocks');
        for (const alert of alerts) {
            const messages = document.getElementById('messages');
            messages === null || messages === void 0 ? void 0 : messages.removeChild(alert);
        }
    }
}
// window.GravAdmin is set by Admin when user has logged in
if ('GravAdmin' in window) {
    const admin = new PageLocksAdmin();
    admin.clearAlerts();
    void (async () => {
        await admin.readLocks();
    })();
}
export {};
//# sourceMappingURL=pagelocks-admin.js.map