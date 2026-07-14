interface HeaderFieldProps {
    children: React.ReactNode;
}

function Header({ children }: HeaderFieldProps) {
    return (
        <header className="sticky top-0 z-10 flex h-header shrink-0 items-center justify-between border-b border-header-border bg-background/80 px-5 backdrop-blur-xl">
            {children}
        </header>
    );
}

export default Header;
