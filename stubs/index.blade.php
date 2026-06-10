@extends('layouts.app')

@section('header')
    <div class="row align-items-center mb-7">
        <div class="col-auto">
            <div class="avatar avatar-xl rounded text-primary">
                <span class="fs-2 material-symbols-outlined text-primary">group</span>
            </div>
        </div>
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    @if (route_back())
                        <li class="breadcrumb-item">
                            <a class="text-body-secondary" href="{{ route_back() }}">
                                <span class="material-symbols-outlined ">keyboard_backspace</span>
                                Voltar
                            </a>
                        </li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">Usuários</li>
                </ol>
            </nav>

            <h1 class="fs-4 mb-0">Usuários</h1>
        </div>

        <div class="col-12 col-sm-auto mt-4 mt-sm-0">
            <a class="btn btn-outline-light d-block border" href="{{ route('admin.users.create') }}">
                <span class="material-symbols-outlined me-1">add </span>
                Novo Usuário
            </a>
        </div>
    </div>
@endsection

@section('content')
    <!-- Page content -->
    <div class="row">
        <div class="col-12">
            <!-- Filters -->
            <div class="card card-line bg-body-tertiary border-transparent mb-7">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                            {{--
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="text-body-secondary">3 contacts selected</div>
                                </div>
                                <div class="col-auto border-start border-dark">
                                    <a class="badge text-bg-danger" href="#">
                                        <span class="material-symbols-outlined text-light">delete</span>
                                    </a>
                                </div>
                            </div>
                            --}}
                        </div>
                        <div class="col-12 col-lg">
                            <div class="row gx-3">
                                <div class="col col-lg-auto ms-auto">
                                    <div class="input-group bg-body">
                                        <input type="text" class="form-control" placeholder="Buscar" aria-label="Search" aria-describedby="search" />
                                        <span id="search" class="input-group-text">
                                            <span class="material-symbols-outlined">search</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="dropdown">
                                        <button class="btn btn-dark px-3" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                            <span class="material-symbols-outlined">filter_list</span>
                                        </button>
                                        <div class="dropdown-menu rounded-3 p-6">
                                            <h4 class="fs-lg mb-4">Filtros</h4>
                                            <form id="filterForm" style="width: 350px">
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-3">
                                                        <label class="form-label mb-0" for="filterName">Nome</label>
                                                    </div>
                                                    <div class="col-9">
                                                        <input id="tableUser--filter_name" type="text" class="form-control" name="name" />
                                                    </div>
                                                </div>
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-3">
                                                        <label class="form-label mb-0" for="filterMail">Email</label>
                                                    </div>
                                                    <div class="col-9">
                                                        <input id="tableUser--filter_email" type="text" class="form-control" name="email" />
                                                    </div>
                                                </div>

                                                {{--
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-3">
                                                        <label class="form-label mb-0" for="filterCompany">Permissão</label>
                                                    </div>
                                                    <div class="col-9">
                                                        <select id="filterCompany" class="form-select" data-choices='{"placeholder": "some"}'>
                                                            @foreach (_roles() as $role)
                                                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                            @endforeach

                                                        </select>
                                                    </div>
                                                </div>
                                                --}}
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto ms-n2">
                                    <div class="dropdown">
                                        <button class="btn btn-dark px-3" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                            <span class="material-symbols-outlined">sort_by_alpha</span>
                                        </button>
                                        <div class="dropdown-menu rounded-3 p-6">
                                            <h4 class="fs-lg mb-4">Sort</h4>
                                            <form id="filterForm" style="width: 350px">
                                                <div class="row gx-3">
                                                    <div class="col">
                                                        <select id="sort" class="form-select">
                                                            <option value="name">Nome</option>
                                                            <option value="email">Email</option>
                                                            <option value="created_at">Criação</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-auto">
                                                        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                                                            <input id="sortAsc" type="radio" class="btn-check" name="sortRadio" autocomplete="off" checked />
                                                            <label class="btn btn-light" for="sortAsc" data-bs-toggle="tooltip" data-bs-title="Ascending">
                                                                <span class="material-symbols-outlined">arrow_upward</span>
                                                            </label>
                                                            <input id="sortDesc" type="radio" class="btn-check" name="sortRadio" autocomplete="off" />
                                                            <label class="btn btn-light" for="sortDesc" data-bs-toggle="tooltip" data-bs-title="Descending">
                                                                <span class="material-symbols-outlined">arrow_downward</span>
                                                            </label>
                                                        </div>
                                                    </div>


                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive mb-7">
                <table class="table table-selectable align-middle mb-0">
                    <thead>
                        <th style="width: 0px">
                            <div class="form-check">
                                <input id="tableCheckAll" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="tableCheckAll"></label>
                            </div>
                        </th>
                        <th>Nome</th>
                        <th>Criado</th>
                        <th>Status</th>
                        <th></th>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td style="width: 0px">
                                    <div class="form-check">
                                        <input id="tableCheckOne" class="form-check-input" type="checkbox" />
                                        <label class="form-check-label" for="tableCheckOne"></label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar">
                                            <img class="avatar-img" src="{{ asset('panel/images/user_not_image.png') }}" alt="..." />
                                        </div>
                                        <div class="ms-4">
                                            <div>{{ $user->name }}</div>
                                            <div class="fs-sm text-body-secondary">
                                                <a class="text-reset" href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $user->created_at }}
                                </td>
                                <td>
                                    {!! $user->StatusBadge !!}
                                </td>
                                <td class="table-btn">
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="fs-9 action-icon">
                                        <span class="material-symbols-outlined ">
                                            edit_square
                                        </span>
                                    </a>
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="fs-9 action-icon text-danger">
                                        <span class="material-symbols-outlined ">
                                            delete
                                        </span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row align-items-center">
        <div class="col">
            <!-- Text -->
            <p class="text-body-secondary mb-0">1 – 15 ({{ $users->count() }} total)</p>
        </div>
        <div class="col-auto">
            {!! $users->links('vendor.pagination.custom') !!}
        </div>
    </div>
@endsection
