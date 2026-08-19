import AppLogoIcon, { DEFAULT_LOGO_ALT } from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type Props = {
    showText?: boolean;
    useImage?: boolean;
    text?: string;
    alt?: string;
    iconClassName?: string;
};

export default function AppLogo({
    showText = true,
    useImage = false,
    text,
    alt = DEFAULT_LOGO_ALT,
    iconClassName,
}: Props) {
    const logoText = text ?? 'BestPlay';

    return (
        <>
            <div
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center text-primary',
                    iconClassName,
                )}
            >
                {useImage ? (
                    <AppLogoIcon
                        useImage
                        alt={alt}
                        className="size-6"
                        lightSrc="/src/imagens/logo-icon-white.svg"
                        darkSrc="/src/imagens/logo-icon-dark.svg"
                    />
                ) : (
                    <AppLogoIcon className="size-6" />
                )}
            </div>
            {showText && (
                <div className="ml-1.5 min-w-0 text-left text-sm">
                    <span className="truncate leading-none font-semibold">
                        {logoText}
                    </span>
                </div>
            )}
        </>
    );
}
