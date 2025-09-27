<?php

use App\Models\Note;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\InteractionEventController;
use App\Http\Controllers\PrivacyController;

/* ---------------------- INDEX ---------------------- */
Route::middleware('auth')->get('/', function () {
    return view('index');
})->name('index');

/* ---------------------- AUTHENTICATION ---------------------- */
Route::get('/login', [AuthController::class,'showLogin'])->name('login');
Route::post('/login', [AuthController::class,'login']);
Route::get('/register', [AuthController::class,'showRegister'])->name('register');
Route::post('/register', [AuthController::class,'register']);
Route::post('/logout', [AuthController::class,'logout'])->name('logout');

/* ---------------------- PROFILE CONTROLLER ---------------------- */
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/* ---------------------- UPLOAD CONTROLLER ---------------------- */
Route::get('/upload', [FileUploadController::class, 'showForm'])->name('upload.form');
Route::post('/upload', [FileUploadController::class, 'upload'])
    ->name('file.upload')
    ->middleware('auth');

/* ---------------------- CHAT CONTROLLER ---------------------- */
Route::middleware('auth')->group(function () {
    Route::post('/chat/session', [ChatController::class, 'createSession'])
        ->name('chat.session.create')
        ->middleware('auth');

    Route::get('/chat/{session}', [ChatController::class, 'showSession'])
        ->name('chat.session.show');

    Route::post('/chat/{session}/submit', [ChatController::class, 'submitSession'])
        ->name('chat.session.submit');

    Route::get('/chat/history', [ChatController::class, 'history'])
        ->name('chat.history');

    Route::get('/chat/{session}/download', [ChatController::class, 'downloadSession'])
        ->name('chat.session.download')
        ->middleware('auth');

    Route::post('/chat/{session}/generate-questions', [ChatController::class, 'generateQuestions'])
        ->name('chat.generateQuestions');

    Route::get('/chat/{session}/questions', [ChatController::class, 'sessionQuestions'])
        ->middleware('auth');

    Route::post('/chat/{session}/key-concepts', [ChatController::class, 'generateKeyConcepts'])
        ->name('chat.keyConcepts');

    Route::post('/chat/{session}/study-suggestions', [ChatController::class, 'generateStudySuggestions'])
        ->name('chat.studySuggestions');
});

/* ---------------------- LIBRARY CONTROLLER ---------------------- */
Route::get('/library', [LibraryController::class, 'index'])
     ->middleware('auth')
     ->name('library.index');

Route::delete('/library/{id}', [LibraryController::class, 'destroy'])
    ->middleware('auth')
    ->name('library.destroy');

/* ---------------------- ADMIN CONTROLLER ---------------------- */
Route::middleware(['auth','can:admin'])->prefix('admin')->name('admin.')->group(function(){
    Route::get('/', [AdminController::class,'dashboard'])->name('dashboard');
    Route::resource('logs', LogController::class)->only(['index','show','destroy']);
});

/* ---------------------- INTERACTION EVENT CONTROLLER ---------------------- */
Route::post('/api/interaction/batch', [InteractionEventController::class, 'storeBatch'])
     ->middleware('auth')
     ->name('interaction.batch');

/* ---------------------- PRIVACY CONTROLLER ---------------------- */
Route::post('/privacy/embeddings/remove', [PrivacyController::class, 'removeEmbeddings'])
    ->middleware('auth')->name('privacy.remove');

Route::post('/chat/{session}/regenerate', [ChatController::class, 'regenerate'])
    ->middleware('auth')
    ->name('chat.regenerate');

/* ---------------------- DEBUG CONTROLLER ---------------------- */
Route::get('/debug/inspect-subject-json', function (Request $r) {
    if (! app()->environment('local')) abort(403, 'Local only');
    $subject = $r->query('subject', 'Software Testing');
    $subjectSlug = Str::slug($subject, '-');
    $templatesDir = base_path('study_resource_note_ai/study_agent/templates');
    $subjectFile = $templatesDir . DIRECTORY_SEPARATOR . $subjectSlug . '.json';

    if (! File::exists($subjectFile)) {
        return response()->json(['ok' => false, 'error' => 'file_not_found', 'path'=>$subjectFile], 404);
    }

    $content = json_decode(File::get($subjectFile), true) ?: [];
    $preview = array_slice($content, -2);
    $types = [];

    foreach ($preview as $idx => $entry) {
        if (isset($entry['metadata']) && is_array($entry['metadata'])) {
            foreach ($entry['metadata'] as $k => $v) {
                $types[$idx]['metadata'][$k] = gettype($v);
            }
        }
        $types[$idx]['type'] = $entry['type'] ?? null;
    }

    return response()->json([
        'ok' => true,
        'file' => $subjectFile,
        'entries' => count($content),
        'preview' => $preview,
        'types_preview' => $types,
    ]);
}); 