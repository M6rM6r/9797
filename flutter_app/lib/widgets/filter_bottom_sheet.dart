import 'package:flutter/material.dart';

class FilterBottomSheet extends StatefulWidget {
  final String sortBy;
  final bool sortAscending;
  final double minDiscount;
  final double maxDiscount;
  final Function(String sortBy, bool ascending) onSortChanged;
  final Function(double min, double max) onDiscountRangeChanged;

  const FilterBottomSheet({
    super.key,
    required this.sortBy,
    required this.sortAscending,
    required this.minDiscount,
    required this.maxDiscount,
    required this.onSortChanged,
    required this.onDiscountRangeChanged,
  });

  @override
  State<FilterBottomSheet> createState() => _FilterBottomSheetState();
}

class _FilterBottomSheetState extends State<FilterBottomSheet> {
  late String _sortBy;
  late bool _sortAscending;
  late double _minDiscount;
  late double _maxDiscount;

  @override
  void initState() {
    super.initState();
    _sortBy = widget.sortBy;
    _sortAscending = widget.sortAscending;
    _minDiscount = widget.minDiscount;
    _maxDiscount = widget.maxDiscount;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header
              Row(
                children: [
                  Text(
                    'فلترة الكوبونات',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Sort Options
              Text(
                'ترتيب حسب',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 12),
              
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _buildSortChip('usageCount', 'الأكثر استخداماً'),
                  _buildSortChip('discountPercent', 'نسبة الخصم'),
                  _buildSortChip('expiresAt', 'تاريخ الانتهاء'),
                  _buildSortChip('createdAt', 'الأحدث'),
                ],
              ),

              const SizedBox(height: 20),

              // Sort Direction
              Row(
                children: [
                  Text(
                    'اتجاه الترتيب',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const Spacer(),
                  SegmentedButton<bool>(
                    segments: const [
                      ButtonSegment<bool>(
                        value: false,
                        label: Text('تنازلي'),
                        icon: Icon(Icons.arrow_downward),
                      ),
                      ButtonSegment<bool>(
                        value: true,
                        label: Text('تصاعدي'),
                        icon: Icon(Icons.arrow_upward),
                      ),
                    ],
                    selected: {_sortAscending},
                    onSelectionChanged: (Set<bool> selection) {
                      setState(() {
                        _sortAscending = selection.first;
                      });
                      widget.onSortChanged(_sortBy, _sortAscending);
                    },
                  ),
                ],
              ),

              const SizedBox(height: 20),

              // Discount Range
              Text(
                'نطاق الخصم',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 12),

              // Min Discount
              Text(
                'الحد الأدنى للخصم: ${_minDiscount.toInt()}%',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              Slider(
                value: _minDiscount,
                min: 0,
                max: 100,
                divisions: 20,
                label: '${_minDiscount.toInt()}%',
                onChanged: (value) {
                  setState(() {
                    _minDiscount = value;
                    if (_minDiscount > _maxDiscount) {
                      _maxDiscount = _minDiscount;
                    }
                  });
                  widget.onDiscountRangeChanged(_minDiscount, _maxDiscount);
                },
              ),

              const SizedBox(height: 16),

              // Max Discount
              Text(
                'الحد الأقصى للخصم: ${_maxDiscount.toInt()}%',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              Slider(
                value: _maxDiscount,
                min: 0,
                max: 100,
                divisions: 20,
                label: '${_maxDiscount.toInt()}%',
                onChanged: (value) {
                  setState(() {
                    _maxDiscount = value;
                    if (_maxDiscount < _minDiscount) {
                      _minDiscount = _maxDiscount;
                    }
                  });
                  widget.onDiscountRangeChanged(_minDiscount, _maxDiscount);
                },
              ),

              const SizedBox(height: 20),

              // Action Buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        setState(() {
                          _sortBy = 'usageCount';
                          _sortAscending = false;
                          _minDiscount = 0;
                          _maxDiscount = 100;
                        });
                        widget.onSortChanged(_sortBy, _sortAscending);
                        widget.onDiscountRangeChanged(_minDiscount, _maxDiscount);
                      },
                      child: const Text('إعادة تعيين'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('تطبيق'),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSortChip(String value, String label) {
    return FilterChip(
      label: Text(label),
      selected: _sortBy == value,
      onSelected: (selected) {
        if (selected) {
          setState(() {
            _sortBy = value;
          });
          widget.onSortChanged(_sortBy, _sortAscending);
        }
      },
      backgroundColor: Theme.of(context).colorScheme.surface,
      selectedColor: Theme.of(context).colorScheme.primaryContainer,
      checkmarkColor: Theme.of(context).colorScheme.onPrimaryContainer,
    );
  }
}
