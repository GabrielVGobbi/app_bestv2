interface HeaderFieldProps {
    children: React.ReactNode;
}

function Header({ children }: HeaderFieldProps) {
    return (
        <header className="sticky top-0 z-10 flex justify-between bg-header-background p-4">
            {children}
        </header>
    );
}

export default Header;
