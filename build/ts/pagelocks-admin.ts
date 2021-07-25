import { GravAdminConfig } from './gravadmin-config';

interface Lock {
    email: string;
    fullname: string;
    timestamp: number;
}

interface Locks {
    locks: {
        [url: string]: Lock;
    };
    alert: string;
    countAlert: string;
}

interface Answer {
    isLockRemoved: boolean;
    alert: string;
}

declare global {
    interface Window {
        GravAdmin: {
            config: GravAdminConfig;
        };
    }
}

class PageLocksAdmin {
    public async readLocks(): Promise<void> {

        let answer: Locks;

        try {
            const response = await fetch(
                window.location.pathname + '/pagelocks:readLocks',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                }
            );

            if (response.ok) {
                answer = await response.json() as Locks;
            } else {
                this.notify('ReadLocks: No valid response from server.', 'error');

                return;
            }
        } catch (error) {
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

    private displayLocks(data: Locks) {
        const tableRows = document.getElementById('locklist') as HTMLElement;
        tableRows.innerHTML = '';

        Object.keys(data.locks).forEach((url: string, i: number) => {
            const lock: Lock = data.locks[url];
            const date: Date = new Date(lock.timestamp * 1000);

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

            const row = document.getElementById(`row${i}`) as HTMLElement;
            row.addEventListener('click', () => {
                if (window.confirm(data.alert.replace('%s', lock.fullname))) {
                    void this.removeLock(url);
                }
            })
        });
    }

    public async removeLock(url: string) {
        let response: Response;

        try {
            const data = {
                url,
            }

            response = await fetch(
                window.location.pathname + '/pagelocks:forceRemoveLock',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                }
            );
        } catch (error) {
            this.notify('RemoveLock: Unexpected error while accessing the server.', 'error');
            return;
        }

        this.clearAlerts();

        if (response.ok) {
            const answer = await response.json() as Answer;

            if (answer.isLockRemoved) {
                this.notify(answer.alert, 'info');
            } else {
                this.notify(answer.alert, 'error');
            }

            void this.readLocks();
        } else {
            this.notify('No valid response from server.', 'error');
        }
    }

    public notify(message: string, type: 'info' | 'error') {
        const newMessage = document.createElement('div');
        newMessage.className = `${type} alert pagelocks`;

        const newContent = document.createTextNode(message);
        newMessage.appendChild(newContent);

        const messages = document.getElementById('messages');
        messages?.appendChild(newMessage);
    }

    public clearAlerts() {
        const alerts = document.getElementsByClassName('alert pagelocks');

        for (const alert of alerts) {
            const messages = document.getElementById('messages');
            messages?.removeChild(alert);
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