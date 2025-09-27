<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\VectorStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PrivacyController extends Controller
{
    public function removeEmbeddings(Request $request)
    {
        $user = Auth::user();

        $user->embeddings_opt_out = true;
        $user->save();

        $vs = new VectorStore();

        $embRows = DB::table('embeddings')->where('user_id', $user->id)->get();

        foreach ($embRows as $row) {
            $vs->deleteById((int)$row->id);
        }

        DB::table('embeddings')->where('user_id', $user->id)->delete();

        return redirect()->back()->with('success', 'Your embeddings have been removed.');
    }
}
