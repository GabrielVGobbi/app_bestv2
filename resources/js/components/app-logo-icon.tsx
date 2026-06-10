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
            viewBox="0 0 120 120"
            xmlns="http://www.w3.org/2000/svg"
        >
            <circle cx="60" cy="60" r="54" fill="#ededed" />
            <text
                x="60"
                y="78"
                fill="#0d0d0d"
                fontFamily="var(--font-brand), Poppins, sans-serif"
                fontSize="56"
                fontWeight="700"
                textAnchor="middle"
            >
                R
            </text>
        </svg>
    );
}
