<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sudoku Minimalista</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        }
        .loading-spinner { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .number-button {
            transition: all 0.15s ease-out;
        }
        .number-button:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .cell-animation {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* ✅ MEJORAS VISUALES PARA HIGHLIGHTING */
        .sudoku-cell {
            position: relative;
            transition: all 0.15s ease-out;
        }
        
        .sudoku-cell:hover:not(:disabled) {
            transform: scale(1.02);
            z-index: 1;
        }
        
        .cell-same-number {
            animation: pulse-highlight 0.3s ease-out;
        }
        
        @keyframes pulse-highlight {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="sudoku-app"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        const SudokuApp = () => {
            // Función para hacer copia profunda de arrays 2D
            const deepCopyBoard = (board) => board.map(row => [...row]);
            
            const [board, setBoard] = useState(Array(9).fill().map(() => Array(9).fill(0)));
            const [initialBoard, setInitialBoard] = useState(Array(9).fill().map(() => Array(9).fill(0)));
            const [selectedCell, setSelectedCell] = useState(null);
            const [selectedNumber, setSelectedNumber] = useState(null);
            const [isDarkMode, setIsDarkMode] = useState(false);
            const [gameId, setGameId] = useState(null);
            const [loading, setLoading] = useState(true);
            const [difficulty, setDifficulty] = useState('easy');
            const [timer, setTimer] = useState(0);
            const [isPlaying, setIsPlaying] = useState(false);
            const [gameStats, setGameStats] = useState({ hintsUsed: 0, movesCount: 0 });
            const [puzzleCompleted, setPuzzleCompleted] = useState(false);

            const API_BASE = '/Sudoku/public/api';
            const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const getHeaders = () => ({
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            });

            useEffect(() => {
                // ✅ Test inicial de conectividad
                console.log('🚀 Iniciando Sudoku App...');
                console.log('API_BASE configurado:', API_BASE);
                loadNewPuzzle('easy');
            }, []);

            // Timer mejorado - se detiene cuando se completa
            useEffect(() => {
                let interval = null;
                if (isPlaying && !puzzleCompleted) {
                    interval = setInterval(() => setTimer(timer => timer + 1), 1000);
                }
                return () => clearInterval(interval);
            }, [isPlaying, puzzleCompleted]);

            const loadNewPuzzle = async (selectedDifficulty) => {
                setLoading(true);
                setPuzzleCompleted(false);
                console.log(`🔄 Cargando nuevo puzzle: ${selectedDifficulty}`);
                
                try {
                    const response = await fetch(`${API_BASE}/puzzle/new/${selectedDifficulty}`, {
                        method: 'GET',
                        headers: getHeaders()
                    });
                    
                    console.log('📡 Respuesta del servidor:', response.status);
                    
                    if (response.ok) {
                        const data = await response.json();
                        console.log('✅ Datos recibidos:', data);
                        
                        if (data.success && data.puzzle) {
                            const puzzleArray = stringToBoard(data.puzzle.puzzle_string);
                            
                            setBoard(deepCopyBoard(puzzleArray));
                            setInitialBoard(deepCopyBoard(puzzleArray));
                            setGameId(data.game_id);
                            setTimer(0);
                            setIsPlaying(true);
                            setSelectedCell(null);
                            setSelectedNumber(null);
                            setGameStats({ hintsUsed: 0, movesCount: 0 });
                            
                            console.log('🎮 Puzzle cargado exitosamente desde API');
                            console.log('  - Dificultad:', data.puzzle.difficulty_level);
                            console.log('  - Game ID:', data.game_id);
                            console.log('  - Pistas disponibles:', data.puzzle.clues_count);
                        } else {
                            throw new Error('Formato de respuesta inválido');
                        }
                    } else {
                        const errorData = await response.json().catch(() => ({}));
                        console.log('⚠️ Error del servidor:', errorData.error || 'Error desconocido');
                        console.log('🔄 Fallback: Usando puzzle de ejemplo');
                        loadExamplePuzzle();
                    }
                } catch (error) {
                    console.error('❌ Error conectando con API:', error.message);
                    console.log('🔄 Fallback: Usando puzzle de ejemplo');
                    loadExamplePuzzle();
                }
                
                setLoading(false);
            };

            const loadExamplePuzzle = () => {
                const exampleBoard = [
                    [5, 3, 0, 0, 7, 0, 0, 0, 0],
                    [6, 0, 0, 1, 9, 5, 0, 0, 0],
                    [0, 9, 8, 0, 0, 0, 0, 6, 0],
                    [8, 0, 0, 0, 6, 0, 0, 0, 3],
                    [4, 0, 0, 8, 0, 3, 0, 0, 1],
                    [7, 0, 0, 0, 2, 0, 0, 0, 6],
                    [0, 6, 0, 0, 0, 0, 2, 8, 0],
                    [0, 0, 0, 4, 1, 9, 0, 0, 5],
                    [0, 0, 0, 0, 8, 0, 0, 7, 9]
                ];
                console.log('🎮 Cargando puzzle de ejemplo');
                console.log('board inicial:', JSON.stringify(exampleBoard));
                
                setBoard(deepCopyBoard(exampleBoard));
                setInitialBoard(deepCopyBoard(exampleBoard));
                
                console.log('✅ initialBoard configurado:', JSON.stringify(exampleBoard));
                setTimer(0);
                setIsPlaying(true);
                setPuzzleCompleted(false);
            };

            const stringToBoard = (puzzleString) => {
                const board = [];
                for (let i = 0; i < 9; i++) {
                    const row = [];
                    for (let j = 0; j < 9; j++) {
                        row.push(parseInt(puzzleString[i * 9 + j]) || 0);
                    }
                    board.push(row);
                }
                return board;
            };

            const calculateRemainingNumbers = () => {
                const counts = {};
                for (let i = 1; i <= 9; i++) {
                    counts[i] = 0;
                }
                board.forEach(row => {
                    row.forEach(cell => {
                        if (cell !== 0) counts[cell]++;
                    });
                });
                const remaining = {};
                for (let i = 1; i <= 9; i++) {
                    remaining[i] = 9 - counts[i];
                }
                return remaining;
            };

            const remainingNumbers = calculateRemainingNumbers();

            // ✅ FUNCIONES DE HIGHLIGHTING INTELIGENTE MEJORADAS
            const getCellHighlightType = (rowIndex, colIndex) => {
                const currentValue = board[rowIndex][colIndex];
                
                // 1. Celda seleccionada - máxima prioridad
                if (selectedCell && selectedCell.row === rowIndex && selectedCell.col === colIndex) {
                    return 'selected';
                }
                
                // Verificar si está en la misma fila o columna
                const isSameRow = selectedCell && selectedCell.row === rowIndex;
                const isSameCol = selectedCell && selectedCell.col === colIndex;
                const isInRowOrCol = isSameRow || isSameCol;
                
                // 2. Mismo número + fila/columna - prioridad especial
                if (selectedNumber && currentValue === selectedNumber && currentValue !== 0 && isInRowOrCol) {
                    return 'same-number-and-row-col';
                }
                
                // 3. Solo mismo número - alta prioridad
                if (selectedNumber && currentValue === selectedNumber && currentValue !== 0) {
                    return 'same-number';
                }
                
                // 4. Solo misma fila o columna - baja prioridad
                if (isInRowOrCol) {
                    return 'same-row-col';
                }
                
                return 'normal';
            };
            
            const getCellClasses = (rowIndex, colIndex) => {
                const highlightType = getCellHighlightType(rowIndex, colIndex);
                const currentValue = board[rowIndex][colIndex];
                const isOriginal = initialBoard[rowIndex][colIndex] !== 0;
                
                let classes = 'w-10 h-10 border border-gray-400 flex items-center justify-center text-lg font-semibold cursor-pointer cell-animation sudoku-cell ';
                
                // Colores base según modo y tipo de celda
                if (puzzleCompleted) {
                    classes += isDarkMode 
                        ? 'bg-green-900 text-green-300 ' 
                        : 'bg-green-50 text-green-800 ';
                } else if (isOriginal) {
                    classes += isDarkMode 
                        ? 'bg-gray-800 text-gray-300 ' 
                        : 'bg-gray-100 text-gray-800 ';
                } else {
                    classes += isDarkMode 
                        ? 'bg-gray-900 text-white hover:bg-gray-700 ' 
                        : 'bg-white text-blue-600 hover:bg-gray-50 ';
                }
                
                // Highlighting según tipo (solo si no está completado)
                if (!puzzleCompleted) {
                    switch (highlightType) {
                        case 'selected':
                            classes += isDarkMode 
                                ? 'ring-2 ring-blue-400 bg-gray-700 shadow-lg ' 
                                : 'ring-2 ring-blue-500 bg-blue-50 shadow-lg ';
                            break;
                        case 'same-number-and-row-col':
                            classes += isDarkMode 
                                ? 'bg-blue-700 ring-2 ring-blue-300 cell-same-number ' 
                                : 'bg-blue-200 ring-2 ring-blue-400 cell-same-number ';
                            break;
                        case 'same-number':
                            classes += isDarkMode 
                                ? 'bg-blue-800 ring-1 ring-blue-400 cell-same-number ' 
                                : 'bg-blue-100 ring-1 ring-blue-300 cell-same-number ';
                            break;
                        case 'same-row-col':
                            classes += isDarkMode 
                                ? 'bg-gray-700 ring-1 ring-gray-500 ' 
                                : 'bg-blue-50 ring-1 ring-blue-200 ';
                            break;
                    }
                }
                
                // Bordes del Sudoku
                if (rowIndex % 3 === 0) classes += 'border-t-2 border-t-gray-800 ';
                if (colIndex % 3 === 0) classes += 'border-l-2 border-l-gray-800 ';
                if (rowIndex === 8) classes += 'border-b-2 border-b-gray-800 ';
                if (colIndex === 8) classes += 'border-r-2 border-r-gray-800 ';
                
                return classes;
            };

            // ✅ MEJORADO: Selección con highlighting automático
            const handleCellClick = (row, col) => {
                if (!puzzleCompleted) {
                    setSelectedCell({ row, col });
                    
                    // Si la celda tiene un número, seleccionar ese número automáticamente
                    const cellValue = board[row][col];
                    if (cellValue !== 0) {
                        setSelectedNumber(cellValue);
                        console.log(`🎨 HIGHLIGHTING TRIPLE ACTIVADO:`);
                        console.log(`  - Celda seleccionada: (${row}, ${col}) con número ${cellValue}`);
                        console.log(`  - 🔵 Auto-resaltando todas las celdas con número ${cellValue}`);
                        console.log(`  - 🟦 Resaltando fila ${row + 1} (azul claro)`);
                        console.log(`  - 🟦 Resaltando columna ${col + 1} (azul claro)`);
                        console.log(`  - 🔷 Las celdas con mismo número EN fila/columna tendrán doble resaltado`);
                    } else {
                        console.log(`🎨 HIGHLIGHTING FILA/COLUMNA ACTIVADO:`);
                        console.log(`  - Celda seleccionada: (${row}, ${col}) - celda vacía`);
                        console.log(`  - 🟦 Resaltando toda la fila ${row + 1}`);
                        console.log(`  - 🟦 Resaltando toda la columna ${col + 1}`);
                    }
                }
            };

            // Verificar si el puzzle está completo
            const isPuzzleComplete = (board) => {
                return board.every(row => row.every(cell => cell !== 0));
            };

            // Completar puzzle automáticamente
            const checkAndCompletePuzzle = (newBoard) => {
                if (isPuzzleComplete(newBoard)) {
                    setPuzzleCompleted(true);
                    setIsPlaying(false);
                    
                    setTimeout(() => {
                        alert(`🎉 ¡FELICITACIONES! 🎉\n\n✅ Puzzle completado en: ${formatTime(timer)}\n🎯 Dificultad: ${difficulty.charAt(0).toUpperCase() + difficulty.slice(1)}\n🎮 Movimientos: ${gameStats.movesCount}\n⭐ ¡Excelente trabajo!`);
                    }, 100);
                    
                    return true;
                }
                return false;
            };

            const handleNumberClick = (number) => {
                console.log('=== CLICK EN NÚMERO ===', number);
                console.log('selectedCell:', selectedCell);
                console.log('puzzleCompleted:', puzzleCompleted);
                
                if (selectedCell) {
                    console.log('🔍 ANTES de colocar número:');
                    console.log('  initialBoard[' + selectedCell.row + '][' + selectedCell.col + ']:', initialBoard[selectedCell.row][selectedCell.col]);
                    console.log('  board[' + selectedCell.row + '][' + selectedCell.col + ']:', board[selectedCell.row][selectedCell.col]);
                }
                
                // Solo permitir colocar números en celdas editables (no del puzzle original)
                if (selectedCell && !puzzleCompleted && initialBoard[selectedCell.row][selectedCell.col] === 0) {
                    console.log('✅ Colocando número', number, 'en celda', selectedCell);
                    const newBoard = deepCopyBoard(board);
                    const oldValue = newBoard[selectedCell.row][selectedCell.col];
                    newBoard[selectedCell.row][selectedCell.col] = number;
                    
                    console.log('🔄 ACTUALIZANDO board...');
                    console.log('  Valor anterior:', oldValue);
                    console.log('  Valor nuevo:', number);
                    console.log('  ✅ ANTES - initialBoard[' + selectedCell.row + '][' + selectedCell.col + ']:', initialBoard[selectedCell.row][selectedCell.col]);
                    
                    setBoard(newBoard);
                    
                    // ✅ VERIFICAR que initialBoard NO cambió después del setState
                    setTimeout(() => {
                        console.log('  ✅ DESPUÉS - initialBoard[' + selectedCell.row + '][' + selectedCell.col + ']:', initialBoard[selectedCell.row][selectedCell.col]);
                        console.log('  ✅ newBoard[' + selectedCell.row + '][' + selectedCell.col + ']:', newBoard[selectedCell.row][selectedCell.col]);
                    }, 10);
                    
                    if (oldValue !== number) {
                        setGameStats(prev => ({ 
                            ...prev, 
                            movesCount: prev.movesCount + 1 
                        }));
                    }
                    
                    checkAndCompletePuzzle(newBoard);
                } else {
                    console.log('❌ No se puede colocar número:');
                    console.log('  - selectedCell:', !!selectedCell);
                    console.log('  - puzzleCompleted:', puzzleCompleted);
                    console.log('  - es celda editable:', selectedCell ? initialBoard[selectedCell.row][selectedCell.col] === 0 : 'N/A');
                }
                setSelectedNumber(number);
            };

            // ✅ CORREGIDO: Lógica de borrado simplificada
            const handleEraseClick = () => {
                if (!selectedCell || puzzleCompleted) {
                    console.log('No se puede borrar: sin celda seleccionada o puzzle completado');
                    return;
                }

                // Solo permitir borrar si NO es una celda original del puzzle
                if (initialBoard[selectedCell.row][selectedCell.col] !== 0) {
                    console.log('No se puede borrar: es una celda original del puzzle');
                    return;
                }

                // Solo borrar si hay algo que borrar
                if (board[selectedCell.row][selectedCell.col] === 0) {
                    console.log('No hay nada que borrar en esta celda');
                    return;
                }

                const newBoard = deepCopyBoard(board);
                console.log(`Borrando número ${newBoard[selectedCell.row][selectedCell.col]} de celda (${selectedCell.row}, ${selectedCell.col})`);
                newBoard[selectedCell.row][selectedCell.col] = 0;
                setBoard(newBoard);
                setGameStats(prev => ({ 
                    ...prev, 
                    movesCount: prev.movesCount + 1 
                }));
            };

            // ✅ Función auxiliar optimizada con useMemo para evitar loops infinitos
            const canErase = React.useMemo(() => {
                console.log('=== CALCULANDO canErase() ===');
                console.log('selectedCell:', selectedCell);
                
                if (!selectedCell) {
                    console.log('❌ No selectedCell');
                    return false;
                }
                
                if (puzzleCompleted) {
                    console.log('❌ puzzleCompleted:', puzzleCompleted);
                    return false;
                }
                
                const isOriginal = initialBoard[selectedCell.row][selectedCell.col] !== 0;
                const currentValue = board[selectedCell.row][selectedCell.col];
                
                console.log('🔍 DEBUG CRITICO:');
                console.log('  ➡️ initialBoard[' + selectedCell.row + '][' + selectedCell.col + ']:', initialBoard[selectedCell.row][selectedCell.col]);
                console.log('  ➡️ board[' + selectedCell.row + '][' + selectedCell.col + ']:', currentValue);
                console.log('  ➡️ isOriginal (deberia ser false para celdas editables):', isOriginal);
                
                if (isOriginal) {
                    console.log('❌ Es celda original del puzzle - NO SE PUEDE BORRAR');
                    return false;
                }
                
                if (currentValue === 0) {
                    console.log('❌ Celda vacía, no hay nada que borrar');
                    return false;
                }
                
                console.log('✅ PUEDE BORRAR! Celda editable con valor:', currentValue);
                return true;
            }, [selectedCell, puzzleCompleted, initialBoard, board]);

            const formatTime = (seconds) => {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            };

            const handleDifficultyChange = (newDifficulty) => {
                setDifficulty(newDifficulty);
                loadNewPuzzle(newDifficulty);
            };

            // Verificar conflictos
            const hasConflict = (row, col, num) => {
                if (num === 0) return false;

                for (let c = 0; c < 9; c++) {
                    if (c !== col && board[row][c] === num) return true;
                }

                for (let r = 0; r < 9; r++) {
                    if (r !== row && board[r][col] === num) return true;
                }

                const startRow = Math.floor(row / 3) * 3;
                const startCol = Math.floor(col / 3) * 3;
                for (let r = startRow; r < startRow + 3; r++) {
                    for (let c = startCol; c < startCol + 3; c++) {
                        if ((r !== row || c !== col) && board[r][c] === num) return true;
                    }
                }

                return false;
            };

            // Controles de teclado
            useEffect(() => {
                const handleKeyPress = (e) => {
                    if (!selectedCell || puzzleCompleted) return;
                    
                    if (e.key >= '1' && e.key <= '9') {
                        handleNumberClick(parseInt(e.key));
                    } else if (e.key === 'Backspace' || e.key === 'Delete' || e.key === '0') {
                        handleEraseClick();
                    } else if (e.key === 'ArrowUp' && selectedCell.row > 0) {
                        setSelectedCell({ ...selectedCell, row: selectedCell.row - 1 });
                    } else if (e.key === 'ArrowDown' && selectedCell.row < 8) {
                        setSelectedCell({ ...selectedCell, row: selectedCell.row + 1 });
                    } else if (e.key === 'ArrowLeft' && selectedCell.col > 0) {
                        setSelectedCell({ ...selectedCell, col: selectedCell.col - 1 });
                    } else if (e.key === 'ArrowRight' && selectedCell.col < 8) {
                        setSelectedCell({ ...selectedCell, col: selectedCell.col + 1 });
                    }
                };

                window.addEventListener('keydown', handleKeyPress);
                return () => window.removeEventListener('keydown', handleKeyPress);
            }, [selectedCell, puzzleCompleted]);

            if (loading) {
                return (
                    <div className="min-h-screen flex items-center justify-center">
                        <div className="text-center">
                            <div className="loading-spinner rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                            <p className="text-gray-600">Cargando puzzle...</p>
                        </div>
                    </div>
                );
            }

            return (
                <div className={`min-h-screen transition-colors duration-300 ${
                    isDarkMode ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-900'
                }`}>
                    {/* Header */}
                    <div className={`border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
                        <div className="max-w-6xl mx-auto px-4 py-4">
                            <div className="flex justify-between items-center">
                                <div className="flex items-center gap-4">
                                    <h1 className="text-2xl font-bold">Sudoku</h1>
                                    <div className="flex items-center gap-2 text-sm">
                                        <span className={`px-2 py-1 rounded-md ${
                                            isDarkMode ? 'bg-gray-700' : 'bg-gray-100'
                                        }`}>
                                            {difficulty.charAt(0).toUpperCase() + difficulty.slice(1)}
                                        </span>
                                        <span className={`font-mono ${puzzleCompleted ? 'text-green-500 font-bold' : ''}`}>
                                            ⏱️ {formatTime(timer)} {puzzleCompleted ? '✅' : ''}
                                        </span>
                                    </div>
                                </div>
                                
                                <div className="flex items-center gap-2">
                                    <select
                                        value={difficulty}
                                        onChange={(e) => handleDifficultyChange(e.target.value)}
                                        className={`px-3 py-1 rounded-md text-sm border ${
                                            isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-white border-gray-300'
                                        }`}
                                    >
                                        <option value="easy">Fácil</option>
                                        <option value="medium">Medio</option>
                                        <option value="hard">Difícil</option>
                                        <option value="expert">Experto</option>
                                        <option value="master">Maestro</option>
                                    </select>
                                    
                                    <button
                                        onClick={() => loadNewPuzzle(difficulty)}
                                        className={`px-3 py-1 rounded-md text-sm font-medium transition-colors ${
                                            isDarkMode 
                                                ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                                : 'bg-blue-500 hover:bg-blue-600 text-white'
                                        }`}
                                    >
                                        Nuevo
                                    </button>
                                    
                                    {/* 📊 Botón de Test API (temporal) */}
                                    <button
                                        onClick={async () => {
                                            console.log('🧪 Testing API...');
                                            try {
                                                const response = await fetch(`${API_BASE}/puzzle/new/easy`);
                                                const data = await response.json();
                                                console.log('📊 API Test Result:', data);
                                                alert(`API Status: ${response.status}\n${data.success ? '✅ Exitoso' : '❌ Error'}`);
                                            } catch (error) {
                                                console.error('❌ API Test Error:', error);
                                                alert('❌ Error conectando con API');
                                            }
                                        }}
                                        className={`px-2 py-1 rounded text-xs ${
                                            isDarkMode ? 'bg-green-600 hover:bg-green-700' : 'bg-green-500 hover:bg-green-600'
                                        } text-white`}
                                        title="Test API"
                                    >
                                        🧪
                                    </button>
                                    
                                    <button
                                        onClick={() => setIsDarkMode(!isDarkMode)}
                                        className={`p-2 rounded-md transition-colors ${
                                            isDarkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-200 hover:bg-gray-300'
                                        }`}
                                    >
                                        {isDarkMode ? '☀️' : '🌙'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="max-w-6xl mx-auto p-4">
                        <div className="flex flex-col lg:flex-row gap-6">
                            {/* Tablero */}
                            <div className="flex-1 flex flex-col items-center">
                                {puzzleCompleted && (
                                    <div className="mb-4 p-4 bg-green-100 border border-green-300 rounded-lg text-green-800 text-center">
                                        <div className="text-lg font-bold">🎉 ¡Puzzle Completado! 🎉</div>
                                        <div className="text-sm mt-1">Tiempo: {formatTime(timer)} | Movimientos: {gameStats.movesCount}</div>
                                    </div>
                                )}

                                <div className={`grid grid-cols-9 gap-0 border-2 rounded-lg overflow-hidden shadow-lg ${
                                    isDarkMode ? 'border-gray-600' : 'border-gray-800'
                                } ${puzzleCompleted ? 'ring-4 ring-green-400' : ''}`}>
                                    {board.map((row, rowIndex) =>
                                        row.map((cell, colIndex) => {
                                            const hasError = hasConflict(rowIndex, colIndex, cell);
                                            const isSubgridBorder = {
                                                borderRight: (colIndex + 1) % 3 === 0 && colIndex !== 8,
                                                borderBottom: (rowIndex + 1) % 3 === 0 && rowIndex !== 8
                                            };

                                            return (
                                                <button
                                                    key={`${rowIndex}-${colIndex}`}
                                                    onClick={() => handleCellClick(rowIndex, colIndex)}
                                                    disabled={puzzleCompleted}
                                                    className={`
                                                        ${getCellClasses(rowIndex, colIndex)}
                                                        w-12 h-12 lg:w-14 lg:h-14 text-lg lg:text-xl
                                                        ${puzzleCompleted ? 'cursor-not-allowed' : ''}
                                                        ${hasError && !puzzleCompleted
                                                            ? isDarkMode 
                                                                ? 'bg-red-900 text-red-300 ring-2 ring-red-500' 
                                                                : 'bg-red-100 text-red-700 ring-2 ring-red-400'
                                                            : ''
                                                        }
                                                        ${isSubgridBorder.borderRight ? 'border-r-2 border-r-gray-800' : ''}
                                                        ${isSubgridBorder.borderBottom ? 'border-b-2 border-b-gray-800' : ''}
                                                    `}
                                                >
                                                    {cell !== 0 ? cell : ''}
                                                </button>
                                            );
                                        })
                                    )}
                                </div>

                                {selectedCell && !puzzleCompleted && (
                                    <div className={`mt-4 p-3 rounded-lg ${
                                        isDarkMode ? 'bg-gray-800' : 'bg-gray-100'
                                    }`}>
                                        <p className="text-sm">
                                            Celda: Fila {selectedCell.row + 1}, Columna {selectedCell.col + 1}
                                            {initialBoard[selectedCell.row][selectedCell.col] !== 0 ? ' (Original del puzzle)' : ' (Editable)'}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Panel de números */}
                            <div className="lg:w-80">
                                <div className={`p-4 rounded-lg shadow-lg ${
                                    isDarkMode ? 'bg-gray-800' : 'bg-white'
                                }`}>
                                    <h3 className="text-lg font-semibold mb-4">Números</h3>
                                    
                                    <div className="grid grid-cols-3 gap-3 mb-4">
                                        {[1, 2, 3, 4, 5, 6, 7, 8, 9].map(number => (
                                            <button
                                                key={number}
                                                onClick={() => handleNumberClick(number)}
                                                disabled={remainingNumbers[number] === 0 || puzzleCompleted}
                                                className={`
                                                    relative h-16 rounded-lg font-bold text-xl number-button
                                                    ${remainingNumbers[number] === 0 || puzzleCompleted
                                                        ? isDarkMode 
                                                            ? 'bg-gray-700 text-gray-500 cursor-not-allowed' 
                                                            : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                                        : selectedNumber === number
                                                            ? isDarkMode 
                                                                ? 'bg-blue-600 text-white ring-2 ring-blue-400' 
                                                                : 'bg-blue-500 text-white ring-2 ring-blue-300'
                                                            : isDarkMode 
                                                                ? 'bg-gray-700 text-white hover:bg-gray-600' 
                                                                : 'bg-gray-100 text-gray-900 hover:bg-gray-200'
                                                    }
                                                `}
                                            >
                                                <span>{number}</span>
                                                <span className={`
                                                    absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs flex items-center justify-center font-medium
                                                    ${remainingNumbers[number] === 0 
                                                        ? isDarkMode ? 'bg-gray-600 text-gray-400' : 'bg-gray-300 text-gray-500'
                                                        : remainingNumbers[number] <= 2
                                                            ? isDarkMode ? 'bg-orange-600 text-white' : 'bg-orange-500 text-white'
                                                            : isDarkMode ? 'bg-green-600 text-white' : 'bg-green-500 text-white'
                                                    }
                                                `}>
                                                    {remainingNumbers[number]}
                                                </span>
                                            </button>
                                        ))}
                                    </div>

                                    {/* ✅ BOTÓN BORRAR OPTIMIZADO */}
                                    <button
                                        onClick={() => {
                                            console.log('=== CLICK EN BOTÓN BORRAR ===');
                                            console.log('canErase:', canErase);
                                            handleEraseClick();
                                        }}
                                        disabled={!canErase}
                                        className={`
                                            w-full h-12 rounded-lg font-semibold number-button
                                            ${!canErase
                                                ? isDarkMode 
                                                    ? 'bg-gray-700 text-gray-500 cursor-not-allowed' 
                                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                                : isDarkMode 
                                                    ? 'bg-red-600 text-white hover:bg-red-700' 
                                                    : 'bg-red-500 text-white hover:bg-red-600'
                                            }
                                        `}
                                    >
                                        🗑️ Borrar {canErase ? '(Habilitado)' : '(Deshabilitado)'}
                                    </button>

                                    {/* Estadísticas */}
                                    <div className={`mt-4 p-3 rounded-lg ${
                                        isDarkMode ? 'bg-gray-700' : 'bg-gray-50'
                                    }`}>
                                        <h4 className="font-medium mb-2">Progreso</h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>Completadas:</span>
                                                <span className="font-mono">
                                                    {81 - board.flat().filter(cell => cell === 0).length}/81
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Progreso:</span>
                                                <span className={`font-mono ${puzzleCompleted ? 'text-green-600 font-bold' : ''}`}>
                                                    {Math.round(((81 - board.flat().filter(cell => cell === 0).length) / 81) * 100)}%
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Movimientos:</span>
                                                <span className="font-mono">{gameStats.movesCount}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Leyenda */}
                                    <div className={`mt-4 p-3 rounded-lg text-xs ${
                                        isDarkMode ? 'bg-gray-700' : 'bg-gray-50'
                                    }`}>
                                        <h4 className="font-medium mb-2">Leyenda:</h4>
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <div className="w-3 h-3 rounded-full bg-green-500"></div>
                                                <span>Disponibles (3+)</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className="w-3 h-3 rounded-full bg-orange-500"></div>
                                                <span>Pocos (≤2)</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className="w-3 h-3 rounded-full bg-gray-400"></div>
                                                <span>Completos (0)</span>
                                            </div>
                                        </div>
                                        <div className="mt-2 pt-2 border-t border-gray-400 text-xs">
                                            <p><strong>Controles:</strong></p>
                                            <p>• Números 1-9: Colocar</p>
                                            <p>• Flechas: Navegar</p>
                                            <p>• Backspace: Borrar</p>
                                            <p>• Clic: Seleccionar celda</p>
                                        </div>
                                    </div>

                                    {/* Debug info */}
                                    {selectedCell && (
                                        <div className={`mt-4 p-2 rounded text-xs ${
                                            isDarkMode ? 'bg-gray-900' : 'bg-gray-100'
                                        }`}>
                                            <div><strong>Debug:</strong></div>
                                            <div>Celda: ({selectedCell.row}, {selectedCell.col})</div>
                                            <div>Original: {initialBoard[selectedCell.row][selectedCell.col] !== 0 ? 'Sí' : 'No'}</div>
                                            <div>Valor actual: {board[selectedCell.row][selectedCell.col]}</div>
                                            <div>Puede borrar: {canErase ? 'Sí' : 'No'}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        };

        ReactDOM.render(<SudokuApp />, document.getElementById('sudoku-app'));
    </script>
</body>
</html>