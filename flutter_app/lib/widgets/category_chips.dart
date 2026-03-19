import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';

class CategoryChips extends StatefulWidget {
  final String selectedCategory;
  final Function(String) onCategoryChanged;

  const CategoryChips({
    super.key,
    required this.selectedCategory,
    required this.onCategoryChanged,
  });

  @override
  State<CategoryChips> createState() => _CategoryChipsState();
}

class _CategoryChipsState extends State<CategoryChips> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  List<Map<String, dynamic>> _categories = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  Future<void> _loadCategories() async {
    try {
      QuerySnapshot snapshot = await _firestore
          .collection('categories')
          .where('isActive', isEqualTo: true)
          .orderBy('sortOrder')
          .get();

      final categories = snapshot.docs.map((doc) {
        final data = doc.data() as Map<String, dynamic>;
        return {
          'id': doc.id,
          'name': data['name'] ?? '',
          'slug': data['slug'] ?? '',
          'iconUrl': data['iconUrl'] ?? '',
        };
      }).toList();

      // Add "All" category at the beginning
      categories.insert(0, {
        'id': 'all',
        'name': 'الكل',
        'slug': 'all',
        'iconUrl': '',
      });

      if (mounted) {
        setState(() {
          _categories = categories;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return SizedBox(
        height: 50,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: 5,
          itemBuilder: (context, index) {
            return Container(
              margin: const EdgeInsets.only(left: 8),
              width: 80,
              height: 36,
              decoration: BoxDecoration(
                color: Colors.grey[200],
                borderRadius: BorderRadius.circular(18),
              ),
            );
          },
        ),
      );
    }

    return SizedBox(
      height: 50,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: _categories.length,
        itemBuilder: (context, index) {
          final category = _categories[index];
          final isSelected = widget.selectedCategory == category['name'];

          return Container(
            margin: const EdgeInsets.only(left: 8),
            child: FilterChip(
              selected: isSelected,
              label: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (category['iconUrl'] != null && category['iconUrl'].isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(left: 6),
                      child: Image.network(
                        category['iconUrl'],
                        width: 16,
                        height: 16,
                        errorBuilder: (context, error, stackTrace) {
                          return Icon(
                            _getCategoryIcon(category['slug']),
                            size: 16,
                          );
                        },
                      ),
                    )
                  else
                    Padding(
                      padding: const EdgeInsets.only(left: 6),
                      child: Icon(
                        _getCategoryIcon(category['slug']),
                        size: 16,
                      ),
                    ),
                  Text(category['name']),
                ],
              ),
              onSelected: (selected) {
                if (selected) {
                  widget.onCategoryChanged(category['name']);
                }
              },
              backgroundColor: Colors.grey[100],
              selectedColor: Theme.of(context).colorScheme.primary.withOpacity(0.2),
              checkmarkColor: Theme.of(context).colorScheme.primary,
              labelStyle: TextStyle(
                color: isSelected 
                    ? Theme.of(context).colorScheme.primary 
                    : Theme.of(context).colorScheme.onSurface,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
              ),
              side: BorderSide(
                color: isSelected 
                    ? Theme.of(context).colorScheme.primary 
                    : Colors.grey[300]!,
                width: 1,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
          );
        },
      ),
    );
  }

  IconData _getCategoryIcon(String slug) {
    switch (slug) {
      case 'fashion':
        return Icons.checkroom;
      case 'electronics':
        return Icons.devices;
      case 'food':
        return Icons.restaurant;
      case 'travel':
        return Icons.flight;
      case 'all':
        return Icons.apps;
      default:
        return Icons.local_offer;
    }
  }
}
