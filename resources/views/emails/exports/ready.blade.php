@extends('emails.layouts.base')

@section('title', 'Exportação concluída')

@section('content')
    <h1>Seu arquivo está pronto!</h1>

    <p>Olá, {{ $userName ?? 'usuário' }}!</p>

    <p>
        A exportação que você solicitou foi concluída com sucesso.
        Clique no botão abaixo para baixar o arquivo.
    </p>

    <p>
        <a href="{{ $downloadUrl }}" class="btn-primary">Baixar arquivo</a>
    </p>

    <div class="info-box">
        <p>
            ⏳ <strong>Atenção:</strong> Este link expira em
            <strong>{{ $expiresAt }}</strong>. Faça o download antes dessa data.
        </p>
    </div>

    <hr class="divider">

    <p style="font-size: 13px; color: #a1a1aa;">
        Se você não solicitou esta exportação, pode ignorar este e-mail com segurança.
    </p>
@endsection
