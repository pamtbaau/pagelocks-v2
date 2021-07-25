export interface GravAdminConfig {
    current_url: string;
    base_url_relative: string;
    base_url_simple: string;
    route: string;
    param_sep: string;
    enable_auto_updates_check: string;
    admin_timeout: string;
    admin_nonce: string;
    language: string;
    pro_enabled: string;
    notifications: {
        enabled: number;
        filters: ['feed', 'dashboard', 'top'];
    };
    local_notifications: string;
    site: {
        delimiter: string;
    };
};
