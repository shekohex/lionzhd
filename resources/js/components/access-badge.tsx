import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';

export default function AccessBadge() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user;

    if (user.role === 'admin') {
        return <Badge variant="secondary">{user.is_super_admin ? 'Super-admin' : 'Admin'}</Badge>;
    }

    if (user.subtype !== 'external') {
        return null;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="h-auto p-0 hover:bg-transparent">
                    <Badge variant="outline" className="cursor-pointer gap-1">
                        External
                        <CircleHelp className="h-3 w-3" />
                    </Badge>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>External access limitations</DialogTitle>
                    <DialogDescription>
                        External member accounts have limited download permissions in this workspace.
                    </DialogDescription>
                </DialogHeader>
                <ul className="list-disc space-y-2 pl-5 text-sm">
                    <li>External accounts cannot use server downloads.</li>
                    <li>External accounts cannot run auto-download schedules.</li>
                </ul>
                <p className="text-muted-foreground text-sm">Contact your super-admin to request access.</p>
            </DialogContent>
        </Dialog>
    );
}
