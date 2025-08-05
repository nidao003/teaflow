<table>
    <thead>
    <tr>
        <th style="width: 200px;">订单号</th>
        <th style="width: 160px;">取餐码</th>
        <th style="width: 160px;">杯数</th>
        <th style="width: 160px;">总金额</th>
        <th style="width: 160px;">配送方式</th>
        <th style="width: 160px;">出餐状态</th>
        <th style="width: 160px;">下单时间</th>
    </tr>
    </thead>
    <tbody>
    @foreach($orders as $order)
        <tr>
            <td data-type="string" data-format="#" align="left" >{{  $order->order_number }}</td>
            <td align="left">{{ $order->get_code }}</td>
            <td align="left">{{ $order->total_quantity }}</td>
            <td align="left">{{ $order->total_amount }}</td>
            <td align="left" 
                    @if($order->waiting_at)
                        style="color: darkgreen;" 
                    @else
                        style="color: red;" 
                    @endif    
                >

                @if($order->waiting_at)
                    已出餐
                @else
                    待出餐
                @endif    

            </td>
            <td align="left"
                @if($order->get_time)
                    style="color: darkgreen;" 
                @else
                    style="color: red;" 
                @endif
            >
                @if($order->get_time)
                    已取餐
                @else
                    未取餐
                @endif
            </td>
            <td align="left">{{ $order->created_at }}</td>
        </tr>
        <tr>
            <th style="width: 200px;background-color: #f5f5f5;" ></th>
            <th colspan="2" style="background-color: #f5f5f5;" >名称</th>
            <th style="background-color: #f5f5f5;" ></th> 
            <th style="background-color: #f5f5f5;" >杯数</th>
            <th style="background-color: #f5f5f5;" >小计</th> 
            <th style="background-color: #f5f5f5;" ></th>
        </tr>
        @foreach($order->items as $item)
            <tr>
                <td align="left"></td>
                <td align="left" colspan="2">{{ $item->product_title }}
                    {{ $item->attr_label }}
                    {{ $item->mix_label }} 
                </td>
                <td align="left"></td>
                <td align="left">{{ $item->quantity }}</td>
                <td align="left">{{ $item->price }}</td>   
                <td align="left"></td>
            </tr>
        @endforeach
        
    @endforeach

    <tr>
        <th style="width: 200px;background-color: #f5f5f5;" align="left" >总计</th>
        <th  style="background-color: #f5f5f5;" ></th>
        <th style="background-color: #f5f5f5;" align="left">
            {{$total}}
        </th> 
        <th style="background-color: #f5f5f5;" align="left">
            {{$amount}}
        </th>
        <th style="background-color: #f5f5f5;" ></th> 
        <th style="background-color: #f5f5f5;" ></th> 
        <th style="background-color: #f5f5f5;" ></th>
    </tr>
    
    </tbody>
</table>