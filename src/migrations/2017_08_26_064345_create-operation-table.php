<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOperationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /**
         * 入库单
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('instock', function(Blueprint $table) {
            $table->increments('id');
            $table->char('code', 32)->comment('编号');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->unsigned()->comment('所属组织ID');
            $table->integer('total_amount')->comment('单据金额:分');
            $table->tinyInteger('genre')->default(0)->unsigned()
                ->comment('入库类型 2:销售退货 4:调拨入库 6:门店退仓驳回 8:门店要货入库 10:加工成品 12:配送差异确认 14:配送差异驳回 16:采购 18:盘点入库 20:红冲入库 22:配送中心直配入库 24:配送中心销售入库');
            $table->integer('creator_id')->unsigned()->comment('制单人ID');
            $table->string('creator_name',32)->nullable()->comment('制单人姓名');
            $table->integer('related_id')->nullable()->comment('关联单据id');
            $table->char('related_code', 32)->nullable()->comment('相关单据编码');
            $table->integer('supplier_id')->nullable()->comment('供应商ID');
            $table->string('supplier_name',32)->nullable()->comment('供应商姓名');
            $table->integer('update_from_id')->nullable()->comment('更新人id');
            $table->string('update_from_name',32)->nullable()->comment('更新人姓名');
            $table->integer('auditor_id')->nullable()->comment('审核人ID');
            $table->string('auditor_name',32)->nullable()->comment('审核人姓名');
            $table->tinyInteger('confirmed')->default(0)->comment('是否审核：0.未审核，1.已审核');
            $table->datetime('confirmed_date')->nullable()->comment('审核日期');
            $table->datetime('instock_date')->nullable()->comment('入库日期');
            $table->tinyInteger('instock_status')->nullable()->comment('入库处理情况：自定义');
            $table->text('remark')->nullable()->comment('备注');
            $table->tinyInteger('print_num')->default(0)->unsigned()->comment('打印次数');
            $table->tinyInteger('status')->default(1)->comment('状态：-1.删除, 1.正常');
            $table->timestamps();
            $table->comment = '入库单表';
        });

        /**
         * 入库单明细表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('instock_content', function(Blueprint $table) {
            $table->increments('id')->comment('明细ID');
            $table->integer('instock_id')->comment('入库单ID');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->string('product_code', 32)->nullable()->comment('商品编码');
            $table->string('product_name', 32)->nullable()->comment('商品名称');
            $table->string('spec_unit', 32)->comment('销售单位');
            $table->float('spec_num', 16, 3)->comment('要货规格');
            $table->tinyInteger('sale_type')->nullable()->comment('计价类型 1:标品,2:称重');
            $table->integer('price')->comment('单个进价: 分');
            $table->integer('amount')->comment('实入金额:分');
            $table->float('quantity', 16, 3)->unsigned()->comment('实入数量');
            $table->float('package', 16, 3)->unsigned()->comment('实入件数');
            $table->char('remark')->nullable()->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('状态：-1.删除, 1.正常');
            $table->timestamps();
            $table->comment = '入库单明细表';
        });

        /**
         * 出库单
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('outstock', function (Blueprint $table) {
            $table->increments('id')->comment('ID');
            $table->char('code', 32)->comment('编号');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->unsigned()->comment('创建单据的组织ID');
            $table->tinyInteger('genre')->default(0)
                ->comment('出库类型：1:销售出库 3:调拨出库 5:门店退仓出库 7:门店要货出库 9:加工原料 11:门店配送差异  13:网单销售 15:供应商退货 17:报损 19:盘点出库 21:红冲出库 23:配送中心直配出库 25:配送中心销售出库');
            $table->integer('total_amount')->default(0)->comment('单据金额');
            $table->integer('target_orgz_id')->nullable()->comment('出库目标组织ID');
            $table->integer('related_id')->nullable()->comment('相关单据id');
            $table->char('related_code', 32)->nullable()->comment('相关单据编码');
            $table->integer('supplier_id')->nullable()->comment('供应商ID');
            $table->datetime('delivery_date')->nullable()->comment('出库日期');
            $table->tinyInteger('outstock_status')->nullable()->comment('单据状态：0:待收货,1:已收货');
            $table->integer('creator_id')->comment('录入人ID');
            $table->string('creator_name',32)->nullable()->comment('录入人姓名');
            $table->integer('update_from_id')->nullable()->comment('更新人id');
            $table->string('update_from_name',32)->nullable()->comment('更新人姓名');
            $table->integer('auditor_id')->nullable()->comment('审核人ID');
            $table->string('auditor_name',32)->nullable()->comment('审核人姓名');
            $table->tinyInteger('confirmed')->default(0)->comment('是否审核：0.未审核，1.已审核');
            $table->datetime('confirmed_date')->nullable()->comment('审核日期');
            $table->text('remark')->nullable()->comment('备注');
            $table->tinyInteger('print_num')->default(0)->unsigned()->comment('打印次数');
            $table->tinyInteger('status')->default(1)->comment('状态: -1.删除, 1.启用');
            $table->timestamps();
            $table->comment = '出库单表';
        });

        /**
         * 出库单明细表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('outstock_content', function (Blueprint $table) {
            $table->increments('id')->comment('明细ID');
            $table->integer('outstock_id')->unsigned()->comment('出库单ID');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->string('product_code', 32)->nullable()->comment('商品编码');
            $table->string('product_name', 32)->nullable()->comment('商品名称');
            $table->float('spec_num', 16, 3)->comment('规格');
            $table->string('spec_unit', 32)->comment('最小销售规格单位');
            $table->tinyInteger('sale_type')->nullable()->comment('计价类型 0:标品,1:称重');
            $table->integer('delivery_price')->nullable()->comment('配送价格: 单位分');
            $table->integer('delivery_amount')->nullable()->comment('配送金额: 单位分');
            $table->integer('price')->comment('成本价格: 分');
            $table->integer('amount')->comment('成本金额');
            $table->float('package', 16, 3)->comment('出库件数');
            $table->float('quantity', 16, 3)->comment('出库数量');
            $table->integer('batch_content_id')->nullable()->comment('相关批次');
            $table->text('remark')->nullable()->comment('备注');
            $table->tinyInteger('status')->default(1)->comment('状态: -1.删除, 1.启用');
            $table->timestamps();
            $table->comment = '出库单明细表';
        });

        /**
         * 库存表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('stock', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->unsigned()->comment('库存所属组织ID');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->integer('price')->comment('最新批次单价：分');
            $table->integer('total_amount')->default(0)->comment('库存金额：分');
            $table->float('quantity', 16, 3)->comment('库存数量');
            $table->float('spec_num', 16, 3)->comment('批次规格');
            $table->string('spec_unit', 32)->comment('批次单位');
            $table->float('instock_num', 16, 3)->default(0)->comment('累计入库数量');
            $table->float('outstock_num', 16, 3)->default(0)->comment('累计出库数量');
            $table->float('sales_num', 16, 3)->default(0)->comment('累计销售数量');
            $table->datetime('last_instock_date')->nullable()->comment('最后入库日期');
            $table->datetime('last_sales_date')->nullable()->comment('最后销售日期');
            $table->datetime('last_outstock_date')->nullable()->comment('最后销售日期');
            $table->timestamps();
            $table->comment = '库存表';
        });

        /**
         * 库存批次表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('stock_batch', function (Blueprint $table) {
            $table->increments('id')->comment('ID');
            $table->char('code', 32)->comment('批次编号');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->comment('组织ID');
            $table->integer('related_id')->comment('相关单据ID：根据单据类型判断');
            $table->tinyInteger('genre')->default(0)->comment('单据类型:
            出库相关<1:销售出库 3:调拨出库 5:门店退仓出库 7:门店要货出库 9:加工原料 11:门店配送差异  13:网单销售 15:供应商退货 17:报损 19:盘点出库 21:红冲出库 23:配送中心直配出库 25:配送中心销售出库>
            入库相关<2:销售退货 4:调拨入库 6:门店退仓驳回 8:门店要货入库 10:加工成品 12:配送差异确认 14:配送差异驳回 16:采购          18:盘点入库 20:红冲入库 22:配送中心直配入库 24:配送中心销售入库>');
            $table->tinyInteger('status')->default(1)->comment('状态：-1.删除, 1.启用');
            $table->timestamps();
            $table->comment = '库存批次表';
        });

        /**
         * 库存批次明细表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('stock_batch_content', function (Blueprint $table) {
            $table->increments('id')->comment('ID');
            $table->integer('stock_batch_id')->unsigned()->comment('库存批次表id');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->float('spec_num', 16, 3)->nullable()->comment('批次规格');
            $table->string('spec_unit', 32)->nullable()->comment('批次单位');
            $table->integer('price')->comment('单价：分');
            $table->integer('total_amount')->comment('商品总价');
            $table->float('quantity', 16, 3)->comment('批次数量');
            $table->float('package', 16, 3)->comment('批次件数');
            $table->float('inventory', 16, 3)->comment('库存数量：值变化直到为0');
            $table->integer('supplier_id')->nullable()->comment('供应商id');
            $table->float('instock_num', 16, 3)->default(0)->comment('入库数量');
            $table->float('outstock_num', 16, 3)->default(0)->comment('出库数量');
            $table->float('sales_num', 16, 3)->default(0)->comment('销售数量');
            $table->tinyInteger('status')->default(1)->comment('状态：-1.删除，1.启用');
            $table->timestamps();
            $table->comment = '库存批次表';
        });

        /**
         * 批次流水表
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('stock_batch_flow', function(Blueprint $table){
            $table->increments('id')->comment('ID');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->unsigned()->comment('所属组织ID');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->float('spec_num', 16, 3)->comment('批次规格');
            $table->string('spec_unit', 32)->comment('批次单位');
            $table->integer('stock_batch_id')->comment('库存批次表ID');
            $table->integer('stock_batch_content_id')->comment('库存批次明细表ID');
            $table->integer('price')->comment('单价：分');
            $table->integer('total_amount')->comment('商品总价');
            $table->float('quantity', 16, 3)->comment('数量');
            $table->float('package', 16, 3)->comment('件数');
            $table->tinyInteger('related_id')->comment('来源单据相关ID');
            $table->tinyInteger('genre')->default(0)->comment('单据类型:
            出库相关<1:销售出库 3:调拨出库 5:门店退仓出库 7:门店要货出库 9:加工原料 11:门店配送差异  13:网单销售 15:供应商退货 17:报损 19:盘点出库 21:红冲出库 23:配送中心直配出库 25:配送中心销售出库>
            入库相关<2:销售退货 4:调拨入库 6:门店退仓驳回 8:门店要货入库 10:加工成品 12:配送差异确认 14:配送差异驳回 16:采购          18:盘点入库 20:红冲入库 22:配送中心直配入库 24:配送中心销售入库>');
            $table->timestamps();
            $table->comment = '批次流水表';
        });

        /**
         * 库存变动记录
         * @author Javen <w@juyii.com>
         * @date 2017-08-29
         */
        Schema::create('stock_change_record', function(Blueprint $table){
            $table->increments('id')->comment('ID');
            $table->char('hq_code', 16)->comment('公司编码');
            $table->integer('orgz_id')->unsigned()->comment('所属组织ID');
            $table->integer('product_id')->unsigned()->comment('商品ID');
            $table->integer('stock_id')->unsigned()->comment('库存ID');
            $table->float('quantity', 16, 3)->comment('变动量');
            $table->float('changed_inventory', 16, 3)->comment('变动后库存');
            $table->integer('related_id')->comment('关联单据id');
            $table->tinyInteger('genre')->default(0)->comment('单据类型:
            出库相关<1:销售出库 3:调拨出库 5:门店退仓出库 7:门店要货出库 9:加工原料 11:门店配送差异  13:网单销售 15:供应商退货 17:报损 19:盘点出库 21:红冲出库 23:配送中心直配出库 25:配送中心销售出库>
            入库相关<2:销售退货 4:调拨入库 6:门店退仓驳回 8:门店要货入库 10:加工成品 12:配送差异确认 14:配送差异驳回 16:采购          18:盘点入库 20:红冲入库 22:配送中心直配入库 24:配送中心销售入库>');
            $table->timestamps();
            $table->index(['created_at']);
            $table->comment = '库存变动记录';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_change_record');
        Schema::dropIfExists('stock_batch_flow');
        Schema::dropIfExists('stock_batch_content');
        Schema::dropIfExists('stock_batch');
        Schema::dropIfExists('stock');
        Schema::dropIfExists('instock_content');
        Schema::dropIfExists('instock');
        Schema::dropIfExists('outstock_content');
        Schema::dropIfExists('outstock');
    }
}
