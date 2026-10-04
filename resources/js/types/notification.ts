export type NotificationItem = {
    id: string;
    report_id: number | null;
    ticket_number: string | null;
    status: string | null;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string;
};
