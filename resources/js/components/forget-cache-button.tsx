import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ZapOffIcon } from 'lucide-react';

interface ForgetCacheButtonProps {
    variant?: 'default' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    onForgetCache?: () => void;
}

export default function ForgetCacheButton({
    variant = 'outline',
    size = 'icon',
    onForgetCache,
}: ForgetCacheButtonProps) {
    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant={variant}
                        size={size}
                        onClick={onForgetCache}
                        disabled={onForgetCache === undefined}
                        aria-label={'Forget Cache and Reload'}
                    >
                        <ZapOffIcon />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{'Forget Cache and reload'}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
