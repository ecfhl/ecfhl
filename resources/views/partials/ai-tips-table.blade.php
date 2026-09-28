            <div class="table-card"><div class="table-scroll"><table class="data-table tips-table">
                <thead><tr>@if($position!=='G')<th scope="col">#</th>@endif<th scope="col">{{ $position==='G'?'Goalie':'Player' }}</th><th scope="col">Opponent</th><th scope="col">Status</th>@if($position==='G')<th scope="col">Starting status</th>@endif<th scope="col">Proj. season FPts</th></tr></thead>
                <tbody>@forelse($rows as $player)<tr>
                    @if($position!=='G')<td>{{ $loop->iteration }}</td>@endif
                    <td><strong>{{ $player['name'] }} ({{ $player['team'] }})</strong>@if(!empty($player['ir'])) <span class="tips-ir" title="Injured reserve">IR</span>@endif</td>
                    <td>{{ $player['opponent'] }}</td>
                    <td><span class="pill {{ $player['status']==='FA'?'tips-fa':'tips-waiver' }}">{{ $player['status'] }}</span></td>
                    @if($position==='G')<td><span class="pill">{{ $player['starting_status'] ?? 'Not listed' }}</span></td>@endif
                    <td>{{ isset($player['projected_points']) ? number_format($player['projected_points'], 0) : '—' }}</td>
                </tr>@empty<tr><td colspan="5" class="empty">No qualifying available players in this update.</td></tr>@endforelse</tbody>
            </table></div></div>
