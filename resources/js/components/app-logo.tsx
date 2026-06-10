import { usePage } from '@inertiajs/react';

import AppLogoIcon, { DEFAULT_LOGO_ALT } from '@/components/app-logo-icon';

type Props = {
    showText?: boolean;
    useImage?: boolean;
    text?: string;
    alt?: string;
};

export default function AppLogo({
    showText = true,
    useImage = true,
    text,
    alt = DEFAULT_LOGO_ALT,
}: Props) {
    const { name } = usePage().props;
    const logoText = text ?? name ?? import.meta.env.VITE_APP_NAME ?? 'Laravel';

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-[#0d0d0d] text-[#ededed] shadow-sm ring-1 ring-border dark:bg-[#ededed]">
                {useImage ? (
                    <AppLogoIcon
                        useImage
                        alt={alt}
                        className="size-7"
                        lightSrc="/src/imagens/logo-icon-white.svg"
                        darkSrc="/src/imagens/logo-icon-dark.svg"
                    />
                ) : (
                    <AppLogoIcon className="size-7" />
                )}
            </div>
            {showText && (
                <div className="ml-2 grid flex-1 text-left text-sm">
                    <span className="mb-0.5 truncate leading-tight font-semibold">
                        {logoText}
                    </span>
                </div>
            )}
        </>
    );
}
