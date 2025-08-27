<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }
        
        h1 {
            font-size: 18px;
            text-align: center;
            margin-bottom: 10px;
            color: #1f2937;
        }
        
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            color: #6b7280;
            font-size: 9px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
        }
        
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
            font-size: 9px;
        }
        
        td {
            word-wrap: break-word;
            max-width: 150px;
        }
        
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
        }
        
        .center {
            text-align: center;
        }
        
        .small {
            font-size: 7px;
            color: #6b7280;
        }
        
        .log-name-auth { color: #3b82f6; }
        .log-name-organization { color: #10b981; }
        .log-name-oauth { color: #8b5cf6; }
        .log-name-system { color: #ef4444; }
        .log-name-tenant { color: #f59e0b; }
        .log-name-user { color: #6366f1; }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }
        
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="footer">
        <div>Generated on {{ $generated_at }} | Total Records: {{ number_format($total_records) }} | Page <span class="page-number"></span></div>
    </div>

    <h1>{{ $title }}</h1>
    
    <div class="header-info">
        <div>Generated on: {{ $generated_at }}</div>
        <div>Total Records: {{ number_format($total_records) }}</div>
    </div>

    @if(count($data) > 0)
        <table>
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        @foreach($columns as $column)
                            <td>
                                @if($column === 'log_name')
                                    <span class="log-name-{{ $row[$column] ?? 'default' }}">
                                        {{ $row[$column] ?? '' }}
                                    </span>
                                @elseif($column === 'description')
                                    <div class="text-truncate">{{ $row[$column] ?? '' }}</div>
                                @elseif($column === 'properties')
                                    <div class="small text-truncate">
                                        {{ is_string($row[$column]) ? $row[$column] : json_encode($row[$column]) }}
                                    </div>
                                @elseif(in_array($column, ['created_at', 'updated_at']))
                                    <div class="small">{{ $row[$column] ?? '' }}</div>
                                @else
                                    {{ $row[$column] ?? '' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="center" style="margin-top: 50px; color: #6b7280;">
            <p>No activity log data found.</p>
        </div>
    @endif
</body>
</html>