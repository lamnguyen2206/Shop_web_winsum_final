# Kịch bản demo bảo vệ đồ án Winsum Home

## Chuẩn bị trước khi demo

- Mở XAMPP, bật Apache và MySQL.
- Import database `winsumwebfinal` nếu máy mới.
- Mở `http://localhost/winsumweb/index.php`.
- Chuẩn bị sẵn một tài khoản khách, tài khoản admin, một sản phẩm còn hàng và mã giảm giá `WINSUMXINCHAO`.
- Chuẩn bị thêm một đơn `pending`, một đơn `shipped` và một đơn `delivered` để minh họa quy tắc hủy đơn / đánh giá.

## Kịch bản 5-7 phút

1. Giới thiệu nhanh hệ thống
   - Website bán nội thất và chiếu sáng.
   - Có hai vai trò chính: khách hàng và quản trị viên.
   - Luồng chính: xem sản phẩm, giỏ hàng, đặt hàng, thanh toán VietQR demo, quản trị đơn và tồn kho.

2. Khách hàng mua hàng
   - Mở danh mục sản phẩm.
   - Tìm kiếm hoặc lọc sản phẩm.
   - Mở chi tiết sản phẩm, xem ảnh, mô tả và đánh giá.
   - Thêm sản phẩm vào giỏ hàng.

3. Giỏ hàng và checkout
   - Cập nhật số lượng hoặc xóa sản phẩm nếu cần.
   - Áp mã giảm giá `WINSUMXINCHAO`.
   - Vào thanh toán.
   - Nhập SĐT hợp lệ 10 số bắt đầu bằng 0.
   - Nhập địa chỉ theo 3 trường: tỉnh/thành phố, phường/xã, địa chỉ cụ thể.
   - Chọn chuyển khoản VietQR.

4. Thanh toán VietQR demo
   - Sau khi đặt hàng, hệ thống hiển thị mã QR.
   - Nêu rõ đây là bản demo: thực tế cần webhook ngân hàng để xác nhận tự động.
   - Bấm “Tôi đã thanh toán”.
   - Hệ thống chuyển đơn sang `Đã thanh toán`.
   - Nếu khách đăng nhập: chuyển sang “Đơn hàng của tôi”.
   - Nếu khách vãng lai: chuyển sang trang tra cứu đơn bằng mã đơn và SĐT.
   - Nêu quy tắc nghiệp vụ: đơn đã hủy / hoàn trả không thể xác nhận VietQR nữa.

5. Quản trị xử lý đơn
   - Đăng nhập admin.
   - Mở quản trị đơn hàng.
   - Tìm đơn theo mã đơn hoặc SĐT.
   - Vào chi tiết đơn, kiểm tra thông tin khách, địa chỉ, thanh toán, sản phẩm.
   - Cập nhật trạng thái giao hàng sang **Đã giao**.
   - Giải thích hệ thống đồng bộ `Trạng thái đơn` và `Trạng thái giao hàng`; đơn COD tự chuyển **Đã thanh toán** khi giao xong.
   - Thử hủy đơn đã giao vận ở phía khách để chứng minh hệ thống không cho hủy sai nghiệp vụ.
   - Mở dashboard: giải thích **Doanh thu thuần** = đã giao + đã thu.

6. Quản trị sản phẩm và tồn kho
   - Mở quản trị sản phẩm.
   - Tìm kiếm sản phẩm theo tên/SKU/danh mục.
   - Chọn “Thêm sản phẩm” để mở trang thêm riêng.
   - Chọn “Sửa chi tiết” để mở trang sửa riêng của đúng sản phẩm.
   - Sửa tồn kho hoặc thông tin sản phẩm, giải thích tồn kho được lưu cùng transaction với sản phẩm.
   - Giải thích cảnh báo tồn kho khi sản phẩm hết hàng sau đơn.

7. Hoàn hàng và nội dung phụ trợ
   - Khách đăng nhập, mở đơn **đã giao**, bấm **Yêu cầu hoàn hàng** (trong hạn 7 ngày), gửi lý do và ảnh minh chứng.
   - Admin mở **Hoàn hàng**, duyệt hoặc từ chối; giải thích admin không tự đổi trạng thái returned trên danh sách đơn.
   - Mở sản phẩm trong đơn đã giao để gửi đánh giá.
   - Giải thích khách chỉ được đánh giá sản phẩm đã mua và đơn đã giao thành công; mỗi sản phẩm chỉ đánh giá một lần.
   - Minh họa đánh giá hiển thị ngay trên tab đánh giá và rating sản phẩm cập nhật tức thì (không cần admin duyệt).
   - Mở quản trị đánh giá để xóa nội dung không phù hợp nếu cần.
   - Mở quản trị blog/bình luận để duyệt nội dung.
   - Mở báo cáo kiểm thử Excel trong `docs/bao-cao-kiem-thu.xlsx`.

## Câu kết luận khi bảo vệ

Hệ thống đã bao phủ đầy đủ nghiệp vụ thương mại điện tử cơ bản: khách hàng mua hàng, đặt hàng, thanh toán demo, theo dõi đơn, yêu cầu hoàn hàng có hạn; quản trị viên quản lý sản phẩm, đơn hàng, tồn kho, khiếu nại hoàn hàng, khách hàng, đánh giá và blog. Các ràng buộc quan trọng như khóa đơn terminal, doanh thu thuần delivered+paid, COD auto paid khi giao, hoàn kho khi hủy/trả, và workflow duyệt hoàn hàng đã được xử lý. Hướng phát triển là tích hợp webhook thanh toán thật, API vận chuyển và kiểm thử tự động.
