interface HeaderFieldProps {
    children: React.ReactNode
}

function Header({
    children,
}: HeaderFieldProps) {
    return (
        <header className="flex bg-header-background sticky top-0 z-10 justify-between p-4">
            {children}
        </header >
    )
}

export default Header
