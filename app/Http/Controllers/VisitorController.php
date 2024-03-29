<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\People;
use App\Models\VisitorHistory;

class VisitorController extends Controller
{
    public function list() {
        $visits = DB::table('visitor_histories as histories')
                    ->join('people as officers', 'officers.id', '=', 'histories.officer_id')
                    ->join('people as visitors', 'visitors.id', '=', 'histories.visitor_id')
                    ->select('histories.*',
                            'officers.name as officer_name',
                            'visitors.name as visitor_name',
                            'visitors.designation as designation',
                            'visitors.phone as phone')
                    ->orderByDesc('histories.created_at')
                    ->paginate(10);
        // DB::raw('SELECT visitor_histories.*, visitor.name, officer.name 
        // FROM visitor_histories 
        // INNER JOIN people AS officer ON officer.id = visitor_histories.officer_id 
        // INNER JOIN people AS visitor ON visitor.id = visitor_histories.officer_id');
        return view('visitor.list', ['visits' => $visits]);
    }

    public function create() {
        $visitor = [
            'name'          => NULL,
            'designation'   => NULL,
            'card'          => NULL,
            'address'       => NULL,
            'phone'         => NULL,
            'nid'           => NULL
        ];
        $officers = People::where('type', 0)->orderBy('designation')->get();
        return view('visitor.create', ['officers' => $officers, 'visitor' => $visitor]);
    }

    public function phone_search() {
        // TODO: add error message if phone number matches with officer
        $formData = request()->validate([
            'phone' => 'required|regex:/^[0-9]{10}$/',
        ]);
        $visitor = People::where('phone', $formData['phone'])->first();
        if (is_null($visitor)) {
            $visitor = [
                'name'          => NULL,
                'designation'   => NULL,
                'card'          => NULL,
                'address'       => NULL,
                'phone'         => $formData['phone'],
                'nid'           => NULL
            ];
        }
        $officers = People::where('type', 0)->orderBy('designation')->get();
        return view('visitor.create', ['officers' => $officers, 'visitor' => $visitor]);
    }

    public function name_search() {
        $formData = request()->validate([
            'name' => 'required',
        ]);
        // it's extact matched to show visit history of a certain user
        $visitor = People::where([['name', $formData['name']], ['type', 1]])->first();
        if (is_null($visitor)) {
            return view('visitor.list', ['visits' => null]);
        } else {
            $visits = DB::table('visitor_histories as histories')
                    ->join('people as officers', 'officers.id', '=', 'histories.officer_id')
                    ->join('people as visitors', 'visitors.id', '=', 'histories.visitor_id')
                    ->select('histories.*',
                            'officers.name as officer_name',
                            'visitors.name as visitor_name',
                            'visitors.designation as designation',
                            'visitors.phone as phone')
                    ->where('visitor_id', $visitor->id)
                    ->orderBy('histories.id')
                    ->orderByDesc('histories.created_at')
                    ->paginate(10);
            return view('visitor.list', ['visits' => $visits]);
        }
    }

    public function store(Request $request) {
        if ($request->user()->cannot('modify', VisitorHistory::class)) {
            abort(403);
        }
        $formData = $request->validate([
            'name'          => 'required|string|max:255',
            'designation'   => 'required|string|max:255',
            'address'       => 'required|string',
            'phone'         => 'required|regex:/^[0-9]{10}$/',
            'nid'           => 'nullable|regex:/^[0-9]{10}$/',
            'card'          => 'required|integer|min:0',
            'officer'       => 'required|integer|min:0',
        ]);
        $visitor = People::where('phone', $formData['phone'])->first();
        if (is_null($visitor)) {
            $visitor = People::create([
                'name'          => $formData['name'],
                'designation'   => $formData['designation'],
                'address'       => $formData['address'],
                'phone'         => $formData['phone'],
                'nid'           => $formData['nid'],
                'type'          => 1,
            ]);
        }        
        $history                = new VisitorHistory;
        $history->card_no       = $formData['card'];
        $history->officer_id    = $formData['officer'];
        $history->visitor_id    = $visitor->id;
        $history->save();

        return redirect(route('visitor.list'))->with('success', 'Visit created!');
    }

    public function report(Request $request) {
        $formData = $request->validate([
            'from'  => 'required|date',
            'to'    => 'required|date',
        ]);
        return redirect(route('visitor.paged_report', ['from' => $formData['from'], 'to' => $formData['to']]));
    }

    public function paged_report($from, $to) {
        $visits = DB::table('visitor_histories as histories')
                    ->join('people as officers', 'officers.id', '=', 'histories.officer_id')
                    ->join('people as visitors', 'visitors.id', '=', 'histories.visitor_id')
                    ->whereDate('histories.created_at', '>=', $from)
                    ->whereDate('histories.created_at', '<=', $to)
                    ->select('histories.*',
                            'officers.name as officer_name',
                            'visitors.name as visitor_name',
                            'visitors.designation as designation',
                            'visitors.phone as phone')
                    ->orderByDesc('histories.created_at')
                    ->paginate(10);
        return view('visitor.paged_report', ['visits' => $visits]);
    }
}
