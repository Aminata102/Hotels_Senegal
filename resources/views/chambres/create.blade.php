<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une Chambre</title>
    <link href="https://jsdelivr.net" rel="stylesheet">
</head>
<body class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white"><h3>Ajouter une nouvelle chambre</h3></div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('chambres.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label>Numéro de chambre</label>
                    <input type="text" name="numero" class="form-control" placeholder="Ex: 101" required>
                </div>
                <div class="mb-3">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="Simple">Simple</option>
                        <option value="Double">Double</option>
                        <option value="Suite">Suite</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Prix par nuitée (FCFA)</label>
                    <input type="number" name="prix_nuitee" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Enregistrer dans la base</button>
            </form>
        </div>
    </div>
</body>
</html>
