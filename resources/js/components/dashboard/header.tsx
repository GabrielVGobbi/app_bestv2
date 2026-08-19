interface HeaderFieldProps {
    children: React.ReactNode;
}

function Header({ children }: HeaderFieldProps) {
    return (
        <header className="flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border bg-background px-4 py-3 md:px-6">
            {children}
        </header>
    );
}

export default Header;
