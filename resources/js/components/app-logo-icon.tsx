import type { ImgHTMLAttributes, SVGAttributes } from 'react';

import { cn } from '@/lib/utils';

type SvgLogoIconProps = SVGAttributes<SVGSVGElement> & {
    useImage?: false;
};

type ImageLogoIconProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'> & {
    useImage: true;
    lightSrc?: string;
    darkSrc?: string;
};

type AppLogoIconProps = SvgLogoIconProps | ImageLogoIconProps;

export const DEFAULT_LOGO_ALT = 'Best Play';

export default function AppLogoIcon(props: AppLogoIconProps) {
    if (props.useImage) {
        const {
            useImage,
            lightSrc = '/src/imagens/logo-icon-dark.svg',
            darkSrc = '/src/imagens/logo-icon-white.svg',
            alt = DEFAULT_LOGO_ALT,
            className,
            ...imageProps
        } = props;

        void useImage;

        return (
            <>
                <img
                    {...imageProps}
                    src={lightSrc}
                    alt={alt}
                    className={cn(
                        'block object-contain dark:hidden',
                        className,
                    )}
                />
                <img
                    {...imageProps}
                    src={darkSrc}
                    alt={alt}
                    className={cn(
                        'hidden object-contain dark:block',
                        className,
                    )}
                />
            </>
        );
    }

    const { useImage, ...svgProps } = props;

    void useImage;

    return (
        <svg
            {...svgProps}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect
                x="3"
                y="11"
                width="4"
                height="10"
                rx="2"
                fill="currentColor"
            />
            <rect
                x="10"
                y="6"
                width="4"
                height="20"
                rx="2"
                fill="currentColor"
            />
            <rect
                x="17"
                y="9"
                width="4"
                height="14"
                rx="2"
                fill="currentColor"
            />
            <rect
                x="24"
                y="13"
                width="4"
                height="6"
                rx="2"
                fill="currentColor"
            />
        </svg>
    );
}
