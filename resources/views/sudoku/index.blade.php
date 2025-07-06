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
            
            // 💡 ESTADO PARA SISTEMA DE PISTAS
            const [hintsRemaining, setHintsRemaining] = useState(3);
            const [lastHint, setLastHint] = useState(null);
            const [showingHint, setShowingHint] = useState(false);
            
            // 💾 ESTADO PARA AUTO-GUARDADO
            const [autoSaveStatus, setAutoSaveStatus] = useState('idle'); // 'idle', 'saving', 'saved', 'error'
            const [lastSaved, setLastSaved] = useState(null);
            const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
            const [showContinueDialog, setShowContinueDialog] = useState(false);
            const [savedGameData, setSavedGameData] = useState(null);
            
            // 🏆 ESTADO PARA SISTEMA DE LOGROS
            const [achievements, setAchievements] = useState([]);
            const [newAchievements, setNewAchievements] = useState([]);
            const [showAchievementModal, setShowAchievementModal] = useState(false);
            const [showAchievementsGallery, setShowAchievementsGallery] = useState(false);
            const [unlockedAchievement, setUnlockedAchievement] = useState(null);
            const [mistakesCount, setMistakesCount] = useState(0);
            
            // 🎵 ESTADO PARA SISTEMA DE SONIDOS
            const [soundEnabled, setSoundEnabled] = useState(() => {
                // Cargar preferencia de localStorage
                const saved = localStorage.getItem('sudoku_sound_enabled');
                return saved !== null ? JSON.parse(saved) : true;
            });
            const [soundVolume, setSoundVolume] = useState(() => {
                // Cargar volumen de localStorage
                const saved = localStorage.getItem('sudoku_sound_volume');
                return saved !== null ? parseFloat(saved) : 0.3;
            });
            
            // Guardar preferencias de sonido
            useEffect(() => {
                localStorage.setItem('sudoku_sound_enabled', JSON.stringify(soundEnabled));
            }, [soundEnabled]);
            
            useEffect(() => {
                localStorage.setItem('sudoku_sound_volume', soundVolume.toString());
            }, [soundVolume]);

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
                
                // 💾 Verificar si hay una partida guardada
                checkForSavedGame();
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
                            
                            // 💡 RESETEAR PISTAS
                            setHintsRemaining(3);
                            setLastHint(null);
                            setShowingHint(false);
                            
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
                
                // 💡 RESETEAR PISTAS
                setHintsRemaining(3);
                setLastHint(null);
                setShowingHint(false);
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

            // ✅ FUNCIONES DE HIGHLIGHTING INTELIGENTE + VALIDACIÓN DE ERRORES + PISTAS
            const getCellHighlightType = (rowIndex, colIndex) => {
                const currentValue = board[rowIndex][colIndex];
                const cellKey = `${rowIndex}-${colIndex}`;
                
                // 0. ERROR - Máxima prioridad (anula todo lo demás)
                if (errorCells.has(cellKey)) {
                    return 'error';
                }
                
                // 0.5. PISTA - Muy alta prioridad
                if (showingHint && lastHint && lastHint.row === rowIndex && lastHint.col === colIndex) {
                    return 'hint';
                }
                
                // 1. Celda seleccionada - alta prioridad
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
                        case 'error':
                            classes += isDarkMode 
                                ? 'bg-red-900 text-red-200 ring-2 ring-red-500 shadow-lg animate-pulse ' 
                                : 'bg-red-100 text-red-800 ring-2 ring-red-500 shadow-lg animate-pulse ';
                            break;
                        case 'hint':
                            classes += isDarkMode 
                                ? 'bg-yellow-900 text-yellow-200 ring-2 ring-yellow-400 shadow-lg animate-bounce ' 
                                : 'bg-yellow-100 text-yellow-800 ring-2 ring-yellow-400 shadow-lg animate-bounce ';
                            break;
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

            // ✅ MEJORADO: Selección con highlighting automático + DEBUG DE ERRORES
            const handleCellClick = (row, col) => {
                if (!puzzleCompleted) {
                    setSelectedCell({ row, col });
                    
                    // 🔴 Debug de errores
                    const cellValue = board[row][col];
                    const cellKey = `${row}-${col}`;
                    const hasError = errorCells.has(cellKey);
                    
                    if (hasError) {
                        console.log(`🔴 CELDA CON ERROR detectada en (${row}, ${col}) con valor ${cellValue}`);
                    }
                    
                    // Si la celda tiene un número, seleccionar ese número automáticamente
                    if (cellValue !== 0) {
                        setSelectedNumber(cellValue);
                        console.log(`🎨 HIGHLIGHTING TRIPLE ACTIVADO:`);
                        console.log(`  - Celda seleccionada: (${row}, ${col}) con número ${cellValue}`);
                        console.log(`  - 🔵 Auto-resaltando todas las celdas con número ${cellValue}`);
                        console.log(`  - 🟦 Resaltando fila ${row + 1} (azul claro)`);
                        console.log(`  - 🟦 Resaltando columna ${col + 1} (azul claro)`);
                        console.log(`  - 🔷 Las celdas con mismo número EN fila/columna tendrán doble resaltado`);
                        if (hasError) console.log(`  - 🔴 ¡ATENCIÓN! Esta celda tiene conflictos`);
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
                    
                    // 🎵 REPRODUCIR SONIDO AL COLOCAR NÚMERO
                    playSound.place();
                    
                    // 🔴 VERIFICAR ERRORES DESPUÉS DE COLOCAR NÚMERO
                    setTimeout(() => {
                        const newErrorCells = getAllErrorCells();
                        if (newErrorCells.size > 0) {
                            console.log(`🔴 ERRORES DETECTADOS después de colocar ${number}:`);
                            newErrorCells.forEach(cellKey => {
                                const [r, c] = cellKey.split('-').map(Number);
                                console.log(`  - Celda (${r}, ${c}) con valor ${board[r][c]}`);
                            });
                            
                            // 🎵 REPRODUCIR SONIDO DE ERROR
                            playSound.error();
                        } else {
                            console.log(`✅ Sin errores después de colocar ${number}`);
                        }
                    }, 10);
                    
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
                    
                    checkAndCompletePuzzleWithAchievements(newBoard);
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
                
                // 🎵 REPRODUCIR SONIDO AL BORRAR
                playSound.erase();
                
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
            
            const formatTimeSince = (date) => {
                const seconds = Math.floor((new Date() - date) / 1000);
                if (seconds < 60) return 'hace pocos segundos';
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `hace ${minutes} min`;
                const hours = Math.floor(minutes / 60);
                return `hace ${hours}h`;
            };
            
            // 💾 SISTEMA DE AUTO-GUARDADO
            
            // Verificar si hay una partida guardada al iniciar
            const checkForSavedGame = async () => {
                console.log('💾 Verificando partidas guardadas...');
                try {
                    const response = await fetch(`${API_BASE}/game/current`, {
                        method: 'GET',
                        headers: getHeaders(),
                        credentials: 'same-origin' // Importante para mantener sesión
                    });
                    
                    console.log('💾 Respuesta de verificación (status):', response.status);
                    
                    if (response.ok) {
                        const data = await response.json();
                        console.log('💾 Datos de verificación:', data);
                        
                        if (data.success && data.game && data.game.status === 'in_progress') {
                            console.log('💾 Partida guardada encontrada:', data.game);
                            setSavedGameData(data.game);
                            setLoading(false); // ✅ IMPORTANTE: Quitar loading antes del modal
                            setShowContinueDialog(true);
                            return;
                        } else {
                            console.log('💾 No hay partidas en progreso:', data.message);
                        }
                    } else {
                        const errorData = await response.json().catch(() => ({}));
                        console.log('💾 Error del servidor al verificar:', errorData);
                    }
                } catch (error) {
                    console.log('💾 Error al verificar partidas guardadas:', error.message);
                }
                
                // Si no hay partida guardada, cargar nueva
                console.log('💾 Cargando nuevo puzzle por defecto...');
                loadNewPuzzle('easy');
            };
            
            // Cargar partida guardada
            const loadSavedGame = () => {
                if (!savedGameData) {
                    console.log('❌ No hay datos de partida guardada');
                    return;
                }
                
                console.log('💾 Cargando partida guardada...');
                console.log('💾 Datos a cargar:', savedGameData);
                
                try {
                    const savedBoard = stringToBoard(savedGameData.current_state);
                    const originalBoard = stringToBoard(savedGameData.initial_state);
                    
                    console.log('💾 Board guardado:', savedBoard);
                    console.log('💾 Board original:', originalBoard);
                    
                    setBoard(deepCopyBoard(savedBoard));
                    setInitialBoard(deepCopyBoard(originalBoard));
                    setGameId(savedGameData.id);
                    setTimer(savedGameData.time_spent || 0);
                    setIsPlaying(true);
                    setGameStats({
                        hintsUsed: savedGameData.hints_used || 0,
                        movesCount: savedGameData.moves_count || 0
                    });
                    setHintsRemaining(3 - (savedGameData.hints_used || 0));
                    setPuzzleCompleted(false);
                    setLoading(false); // ✅ IMPORTANTE: Quitar loading
                    setShowContinueDialog(false); // ✅ IMPORTANTE: Cerrar modal
                    setHasUnsavedChanges(false);
                    setLastSaved(new Date());
                    
                    console.log('✅ Partida guardada cargada exitosamente');
                    console.log('  - Game ID:', savedGameData.id);
                    console.log('  - Tiempo:', savedGameData.time_spent || 0);
                    console.log('  - Movimientos:', savedGameData.moves_count || 0);
                    console.log('  - Pistas usadas:', savedGameData.hints_used || 0);
                } catch (error) {
                    console.error('❌ Error cargando partida guardada:', error);
                    // Si hay error, cargar nuevo puzzle
                    startNewGame();
                }
            };
            
            // Empezar nueva partida (descartar guardado)
            const startNewGame = () => {
                console.log('🆕 Iniciando nueva partida...');
                setShowContinueDialog(false);
                setSavedGameData(null);
                setLoading(true); // ✅ IMPORTANTE: Activar loading
                loadNewPuzzle('easy');
            };
            
            // Auto-guardar el progreso actual
            const autoSaveGame = async () => {
                if (!gameId || puzzleCompleted || !hasUnsavedChanges) {
                    return;
                }
                
                setAutoSaveStatus('saving');
                console.log('💾 Auto-guardando progreso...');
                console.log('  - Game ID:', gameId);
                console.log('  - Board state:', board.flat().join(''));
                
                try {
                    const currentBoardString = board.flat().join('');
                    
                    console.log('💾 Enviando datos de guardado:', {
                        game_id: gameId,
                        current_state: currentBoardString,
                        time_spent: timer,
                        moves_count: gameStats.movesCount,
                        hints_used: gameStats.hintsUsed
                    });
                    
                    const response = await fetch(`${API_BASE}/game/save`, {
                        method: 'POST',
                        headers: getHeaders(),
                        credentials: 'same-origin', // Importante para mantener sesión
                        body: JSON.stringify({
                            game_id: gameId,
                            current_state: currentBoardString,
                            time_spent: timer,
                            moves_count: gameStats.movesCount,
                            hints_used: gameStats.hintsUsed
                        })
                    });
                    
                    console.log('💾 Respuesta del servidor (status):', response.status);
                    
                    if (response.ok) {
                        const data = await response.json();
                        console.log('💾 Respuesta completa:', data);
                        
                        if (data.success) {
                            setAutoSaveStatus('saved');
                            setLastSaved(new Date());
                            setHasUnsavedChanges(false);
                            console.log('✅ Auto-guardado exitoso');
                            
                            // Volver a 'idle' después de 2 segundos
                            setTimeout(() => {
                                setAutoSaveStatus('idle');
                            }, 2000);
                        } else {
                            throw new Error(data.error || 'Error desconocido');
                        }
                    } else {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.error || `Error del servidor: ${response.status}`);
                    }
                } catch (error) {
                    console.error('❌ Error en auto-guardado:', error.message);
                    console.error('❌ Detalles del error:', error);
                    setAutoSaveStatus('error');
                    
                    // Volver a 'idle' después de 3 segundos
                    setTimeout(() => {
                        setAutoSaveStatus('idle');
                    }, 3000);
                }
            };
            
            // Marcar cambios cuando se modifica el board
            useEffect(() => {
                if (gameId && !puzzleCompleted) {
                    setHasUnsavedChanges(true);
                }
            }, [board, timer, gameStats]);
            
            // Auto-guardar cada 10 segundos si hay cambios
            useEffect(() => {
                if (hasUnsavedChanges && !puzzleCompleted) {
                    const autoSaveInterval = setInterval(() => {
                        autoSaveGame();
                    }, 10000); // 10 segundos
                    
                    return () => clearInterval(autoSaveInterval);
                }
            }, [hasUnsavedChanges, puzzleCompleted, gameId]);
            
            // Guardar manualmente
            const saveGameManually = () => {
                autoSaveGame();
            };

            // 💡 SISTEMA DE PISTAS INTELIGENTE
            const getHint = async () => {
                if (hintsRemaining <= 0) {
                    alert('⚠️ Se han agotado las pistas para este puzzle (máximo 3 por juego)');
                    return;
                }
                
                if (puzzleCompleted) {
                    alert('🎉 El puzzle ya está completado. ¡No necesitas más pistas!');
                    return;
                }
                
                console.log('💡 Solicitando pista...');
                console.log('  - Pistas restantes:', hintsRemaining);
                console.log('  - Game ID:', gameId);
                
                try {
                    // Convertir board actual a string para enviar a la API
                    const currentBoardString = board.flat().join('');
                    
                    const response = await fetch(`${API_BASE}/hint`, {
                        method: 'POST',
                        headers: getHeaders(),
                        body: JSON.stringify({
                            game_id: gameId,
                            current_state: currentBoardString
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        
                        if (data.success && data.hint) {
                            const hint = data.hint;
                            
                            console.log('✅ Pista recibida:', hint);
                            console.log('  - Posición:', `(${hint.row}, ${hint.col})`);
                            console.log('  - Número:', hint.number);
                            console.log('  - Explicación:', hint.explanation);
                            
                            // Actualizar estado
                            setLastHint(hint);
                            setShowingHint(true);
                            setHintsRemaining(hintsRemaining - 1);
                            
                            // 🎵 REPRODUCIR SONIDO DE PISTA
                            playSound.hint();
                            
                            // Mostrar explicación
                            alert(`💡 PISTA:\n\n${hint.explanation}\n\n📍 Posición: Fila ${hint.row + 1}, Columna ${hint.col + 1}\n🔢 Número: ${hint.number}\n\nPistas restantes: ${hintsRemaining - 1}/3`);
                            
                            // Seleccionar la celda de la pista
                            setSelectedCell({ row: hint.row, col: hint.col });
                            
                            // Ocultar highlighting de pista después de 5 segundos
                            setTimeout(() => {
                                setShowingHint(false);
                            }, 5000);
                            
                        } else {
                            console.error('❌ Error en respuesta de pista:', data);
                            alert('❌ No se pudo generar una pista. El puzzle podría estar casi completo.');
                        }
                    } else {
                        const errorData = await response.json().catch(() => ({}));
                        console.error('❌ Error del servidor:', errorData);
                        
                        if (response.status === 403) {
                            alert('⚠️ Límite de pistas alcanzado para este puzzle.');
                        } else {
                            alert('❌ Error al obtener pista. Inténtalo de nuevo.');
                        }
                    }
                    
                } catch (error) {
                    console.error('❌ Error conectando con API de pistas:', error);
                    
                    // Fallback: generar pista local básica
                    const localHint = generateLocalHint();
                    if (localHint) {
                        setLastHint(localHint);
                        setShowingHint(true);
                        setHintsRemaining(hintsRemaining - 1);
                        
                        alert(`💡 PISTA (Local):\n\n${localHint.explanation}\n\n📍 Posición: Fila ${localHint.row + 1}, Columna ${localHint.col + 1}\n🔢 Número: ${localHint.number}\n\nPistas restantes: ${hintsRemaining - 1}/3`);
                        
                        setSelectedCell({ row: localHint.row, col: localHint.col });
                        
                        setTimeout(() => {
                            setShowingHint(false);
                        }, 5000);
                    } else {
                        alert('❌ No se pudo generar una pista en este momento.');
                    }
                }
            };
            
            // 🧠 GENERADOR DE PISTAS LOCAL (FALLBACK)
            const generateLocalHint = () => {
                // Buscar una celda vacía
                const emptyCells = [];
                for (let row = 0; row < 9; row++) {
                    for (let col = 0; col < 9; col++) {
                        if (board[row][col] === 0) {
                            emptyCells.push({ row, col });
                        }
                    }
                }
                
                if (emptyCells.length === 0) {
                    return null; // No hay celdas vacías
                }
                
                // Seleccionar una celda aleatoria vacía
                const randomCell = emptyCells[Math.floor(Math.random() * emptyCells.length)];
                
                // Encontrar un número posible (simplificado)
                for (let num = 1; num <= 9; num++) {
                    const conflict = hasConflict(randomCell.row, randomCell.col, num);
                    if (!conflict) {
                        return {
                            row: randomCell.row,
                            col: randomCell.col,
                            number: num,
                            explanation: `En la celda fila ${randomCell.row + 1}, columna ${randomCell.col + 1}, puedes colocar el número ${num}.`
                        };
                    }
                }
                
                return null;
            };
            
            const handleDifficultyChange = (newDifficulty) => {
                setDifficulty(newDifficulty);
                loadNewPuzzle(newDifficulty);
            };

            // 🤖 SISTEMA DE VALIDACIÓN DE ERRORES MEJORADO
            const hasConflict = (row, col, num) => {
                if (num === 0) return false;

                // Verificar fila
                for (let c = 0; c < 9; c++) {
                    if (c !== col && board[row][c] === num) {
                        return { type: 'row', conflictCells: [{row, col: c}] };
                    }
                }

                // Verificar columna
                for (let r = 0; r < 9; r++) {
                    if (r !== row && board[r][col] === num) {
                        return { type: 'column', conflictCells: [{row: r, col}] };
                    }
                }

                // Verificar subcuadro 3x3
                const startRow = Math.floor(row / 3) * 3;
                const startCol = Math.floor(col / 3) * 3;
                const conflictCells = [];
                
                for (let r = startRow; r < startRow + 3; r++) {
                    for (let c = startCol; c < startCol + 3; c++) {
                        if ((r !== row || c !== col) && board[r][c] === num) {
                            conflictCells.push({row: r, col: c});
                        }
                    }
                }
                
                if (conflictCells.length > 0) {
                    return { type: 'box', conflictCells };
                }

                return false;
            };
            
            // 🎯 DETECTAR TODAS LAS CELDAS EN ERROR
            const getAllErrorCells = () => {
                const errorCells = new Set();
                
                for (let row = 0; row < 9; row++) {
                    for (let col = 0; col < 9; col++) {
                        const num = board[row][col];
                        if (num !== 0) {
                            const conflict = hasConflict(row, col, num);
                            if (conflict) {
                                // Añadir la celda actual
                                errorCells.add(`${row}-${col}`);
                                // Añadir todas las celdas en conflicto
                                conflict.conflictCells.forEach(cell => {
                                    errorCells.add(`${cell.row}-${cell.col}`);
                                });
                            }
                        }
                    }
                }
                
                return errorCells;
            };
            
            // 📊 CALCULAR CELDAS EN ERROR UNA SOLA VEZ
            const errorCells = React.useMemo(() => getAllErrorCells(), [board]);

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
            
            // 🎵 SISTEMA DE SONIDOS
            
            // Crear contexto de audio (solo cuando se necesite por primera vez)
            const [audioContext, setAudioContext] = useState(null);
            
            // Inicializar contexto de audio
            const initAudioContext = () => {
                if (!audioContext && soundEnabled) {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    setAudioContext(ctx);
                    return ctx;
                }
                return audioContext;
            };
            
            // Generar sonido de frecuencia específica
            const playTone = (frequency, duration = 0.1, type = 'sine') => {
                if (!soundEnabled) return;
                
                const ctx = initAudioContext();
                if (!ctx) return;
                
                try {
                    const oscillator = ctx.createOscillator();
                    const gainNode = ctx.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(ctx.destination);
                    
                    oscillator.frequency.value = frequency;
                    oscillator.type = type;
                    
                    // Envelope para evitar clicks
                    gainNode.gain.setValueAtTime(0, ctx.currentTime);
                    gainNode.gain.linearRampToValueAtTime(soundVolume, ctx.currentTime + 0.01);
                    gainNode.gain.linearRampToValueAtTime(0, ctx.currentTime + duration);
                    
                    oscillator.start(ctx.currentTime);
                    oscillator.stop(ctx.currentTime + duration);
                } catch (error) {
                    console.log('🎵 Audio no disponible:', error.message);
                }
            };
            
            // Sonidos específicos
            const playSound = {
                // 🔢 Sonido al colocar número (nota musical suave)
                place: () => playTone(440, 0.1, 'sine'), // La 4
                
                // ❌ Sonido de error (disonante pero sutil)
                error: () => playTone(200, 0.15, 'sawtooth'),
                
                // 💡 Sonido de pista (campanita)
                hint: () => {
                    playTone(800, 0.1, 'sine');
                    setTimeout(() => playTone(1000, 0.1, 'sine'), 100);
                },
                
                // 🎉 Sonido de éxito (acorde ascendente)
                success: () => {
                    playTone(523, 0.15, 'sine'); // Do 5
                    setTimeout(() => playTone(659, 0.15, 'sine'), 100); // Mi 5
                    setTimeout(() => playTone(784, 0.2, 'sine'), 200); // Sol 5
                },
                
                // 🏆 Sonido de logro (fanfarria)
                achievement: () => {
                    playTone(523, 0.1, 'sine'); // Do
                    setTimeout(() => playTone(659, 0.1, 'sine'), 80); // Mi
                    setTimeout(() => playTone(784, 0.1, 'sine'), 160); // Sol
                    setTimeout(() => playTone(1047, 0.2, 'sine'), 240); // Do octava
                },
                
                // 🔄 Sonido de acción general (click suave)
                click: () => playTone(600, 0.05, 'sine'),
                
                // 🗑️ Sonido de borrar
                erase: () => playTone(300, 0.08, 'triangle')
            };
            
            // Toggle de sonido
            const toggleSound = () => {
                setSoundEnabled(!soundEnabled);
                if (!soundEnabled) {
                    // Reproducir sonido de confirmación al activar
                    setTimeout(() => playSound.click(), 100);
                }
            };
            
            // Cargar logros del usuario
            const loadUserAchievements = async () => {
                try {
                    const response = await fetch(`${API_BASE}/achievements`, {
                        method: 'GET',
                        headers: getHeaders(),
                        credentials: 'same-origin'
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            setAchievements(data.achievements);
                            console.log('🏆 Logros cargados:', data.achievements.length);
                        }
                    }
                } catch (error) {
                    console.error('❌ Error cargando logros:', error);
                }
            };
            
            // Completar puzzle con verificación de logros
            const completePuzzleWithAchievements = async (finalBoard) => {
                if (!gameId) {
                    console.log('❌ No hay gameId para completar');
                    return;
                }
                
                console.log('🏆 Completando puzzle con verificación de logros...');
                
                try {
                    const response = await fetch(`${API_BASE}/game/complete`, {
                        method: 'POST',
                        headers: getHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            game_id: gameId,
                            current_state: finalBoard.flat().join(''),
                            time_spent: timer,
                            moves_count: gameStats.movesCount,
                            hints_used: gameStats.hintsUsed,
                            mistakes_count: mistakesCount
                        })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        
                        if (data.success) {
                            console.log('✅ Puzzle completado exitosamente');
                            console.log('🏆 Nuevos logros:', data.new_achievements);
                            
                            // 🎵 REPRODUCIR SONIDO DE ÉXITO
                            playSound.success();
                            
                            // Si hay nuevos logros, mostrarlos
                            if (data.new_achievements && data.new_achievements.length > 0) {
                                setNewAchievements(data.new_achievements);
                                setUnlockedAchievement(data.new_achievements[0]); // Mostrar el primero
                                setShowAchievementModal(true);
                                
                                // 🎵 REPRODUCIR SONIDO DE LOGRO
                                setTimeout(() => playSound.achievement(), 500);
                                
                                // Recargar todos los logros
                                loadUserAchievements();
                            }
                            
                            // Mostrar mensaje de felicitación
                            setTimeout(() => {
                                alert(`🎉 ¡FELICITACIONES! 🎉\n\n✅ Puzzle completado en: ${formatTime(timer)}\n🎯 Dificultad: ${difficulty.charAt(0).toUpperCase() + difficulty.slice(1)}\n🎮 Movimientos: ${gameStats.movesCount}\n${data.new_achievements.length > 0 ? `🏆 ¡${data.new_achievements.length} nuevo${data.new_achievements.length > 1 ? 's' : ''} logro${data.new_achievements.length > 1 ? 's' : ''} desbloqueado${data.new_achievements.length > 1 ? 's' : ''}!` : '⭐ ¡Excelente trabajo!'}`);
                            }, 100);
                        }
                    } else {
                        throw new Error('Error al completar puzzle');
                    }
                } catch (error) {
                    console.error('❌ Error completando puzzle:', error);
                    // Fallback al método anterior
                    alert(`🎉 ¡FELICITACIONES! 🎉\n\n✅ Puzzle completado en: ${formatTime(timer)}\n🎯 Dificultad: ${difficulty.charAt(0).toUpperCase() + difficulty.slice(1)}\n🎮 Movimientos: ${gameStats.movesCount}\n⭐ ¡Excelente trabajo!`);
                }
            };
            
            // Cerrar modal de logro y mostrar siguiente si hay más
            const closeAchievementModal = () => {
                setShowAchievementModal(false);
                setUnlockedAchievement(null);
                
                // Si hay más logros pendientes, mostrar el siguiente
                if (newAchievements.length > 1) {
                    const nextAchievements = newAchievements.slice(1);
                    setNewAchievements(nextAchievements);
                    
                    setTimeout(() => {
                        setUnlockedAchievement(nextAchievements[0]);
                        setShowAchievementModal(true);
                    }, 500);
                } else {
                    setNewAchievements([]);
                }
            };
            
            // Mostrar galería de logros
            const toggleAchievementsGallery = () => {
                if (!showAchievementsGallery) {
                    loadUserAchievements(); // Recargar logros antes de mostrar
                }
                setShowAchievementsGallery(!showAchievementsGallery);
            };
            
            // Modificar la función existente checkAndCompletePuzzle para usar la nueva lógica
            const checkAndCompletePuzzleWithAchievements = (newBoard) => {
                if (isPuzzleComplete(newBoard)) {
                    setPuzzleCompleted(true);
                    setIsPlaying(false);
                    
                    // Usar la nueva función con logros
                    completePuzzleWithAchievements(newBoard);
                    
                    return true;
                }
                return false;
            };
            
            // Cargar logros al inicializar
            useEffect(() => {
                loadUserAchievements();
            }, []);

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
                    {/* 💾 MODAL DE CONTINUAR PARTIDA */}
                    {showContinueDialog && (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                            <div className={`p-6 rounded-lg shadow-xl max-w-md w-full mx-4 ${
                                isDarkMode ? 'bg-gray-800 border border-gray-600' : 'bg-white border border-gray-200'
                            }`}>
                                <h3 className="text-lg font-bold mb-4">💾 ¿Continuar partida anterior?</h3>
                                
                                {savedGameData && (
                                    <div className={`mb-4 p-3 rounded-lg ${
                                        isDarkMode ? 'bg-gray-700' : 'bg-gray-50'
                                    }`}>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span>Dificultad:</span>
                                                <span className="font-medium">
                                                    {savedGameData.puzzle?.difficulty_level || 'N/A'}
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Progreso:</span>
                                                <span className="font-medium">
                                                    {savedGameData.current_state ? 
                                                        Math.round(((81 - savedGameData.current_state.split('0').length + 1) / 81) * 100)
                                                        : 0}%
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Tiempo:</span>
                                                <span className="font-medium">
                                                    {formatTime(savedGameData.time_spent || 0)}
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Movimientos:</span>
                                                <span className="font-medium">{savedGameData.moves_count || 0}</span>
                                            </div>
                                        </div>
                                    </div>
                                )}
                                
                                <div className="flex gap-3">
                                    <button
                                        onClick={loadSavedGame}
                                        className={`flex-1 py-2 px-4 rounded-lg font-medium ${
                                            isDarkMode 
                                                ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                                : 'bg-blue-500 hover:bg-blue-600 text-white'
                                        }`}
                                    >
                                        💾 Continuar
                                    </button>
                                    <button
                                        onClick={startNewGame}
                                        className={`flex-1 py-2 px-4 rounded-lg font-medium ${
                                            isDarkMode 
                                                ? 'bg-gray-600 hover:bg-gray-700 text-white border border-gray-500' 
                                                : 'bg-gray-200 hover:bg-gray-300 text-gray-800 border border-gray-300'
                                        }`}
                                    >
                                        🆕 Nueva
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                    
                    {/* 🏆 MODAL DE LOGRO DESBLOQUEADO */}
                    {showAchievementModal && unlockedAchievement && (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                            <div className={`p-6 rounded-lg shadow-xl max-w-md w-full mx-4 text-center ${
                                isDarkMode ? 'bg-gray-800 border border-gray-600' : 'bg-white border border-gray-200'
                            }`}>
                                <div className="text-6xl mb-4 animate-bounce">{unlockedAchievement.icon}</div>
                                
                                <h3 className="text-xl font-bold mb-2 text-yellow-600">
                                    🎉 ¡Logro Desbloqueado!
                                </h3>
                                
                                <h4 className="text-lg font-semibold mb-3">
                                    {unlockedAchievement.name}
                                </h4>
                                
                                <p className={`text-sm mb-6 ${
                                    isDarkMode ? 'text-gray-300' : 'text-gray-600'
                                }`}>
                                    {unlockedAchievement.description}
                                </p>
                                
                                <div className="flex gap-3">
                                    <button
                                        onClick={closeAchievementModal}
                                        className={`flex-1 py-2 px-4 rounded-lg font-medium ${
                                            isDarkMode 
                                                ? 'bg-yellow-600 hover:bg-yellow-700 text-white' 
                                                : 'bg-yellow-500 hover:bg-yellow-600 text-white'
                                        }`}
                                    >
                                        🎉 ¡Genial!
                                    </button>
                                    
                                    <button
                                        onClick={() => {
                                            closeAchievementModal();
                                            toggleAchievementsGallery();
                                        }}
                                        className={`flex-1 py-2 px-4 rounded-lg font-medium border ${
                                            isDarkMode 
                                                ? 'bg-gray-700 hover:bg-gray-600 text-white border-gray-600' 
                                                : 'bg-gray-100 hover:bg-gray-200 text-gray-800 border-gray-300'
                                        }`}
                                    >
                                        🏆 Ver todos
                                    </button>
                                </div>
                                
                                {newAchievements.length > 1 && (
                                    <p className={`text-xs mt-3 ${
                                        isDarkMode ? 'text-gray-400' : 'text-gray-500'
                                    }`}>
                                        🎆 +{newAchievements.length - 1} logro{newAchievements.length > 2 ? 's' : ''} más
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                    
                    {/* 🏆 GALERÍA DE LOGROS */}
                    {showAchievementsGallery && (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                            <div className={`rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden ${
                                isDarkMode ? 'bg-gray-800 border border-gray-600' : 'bg-white border border-gray-200'
                            }`}>
                                <div className={`p-6 border-b ${
                                    isDarkMode ? 'border-gray-700' : 'border-gray-200'
                                }`}>
                                    <div className="flex justify-between items-center">
                                        <h3 className="text-2xl font-bold flex items-center gap-2">
                                            🏆 Logros
                                            <span className={`text-sm px-2 py-1 rounded-md ${
                                                isDarkMode ? 'bg-yellow-800 text-yellow-200' : 'bg-yellow-100 text-yellow-800'
                                            }`}>
                                                {achievements.filter(a => a.is_completed).length}/{achievements.length}
                                            </span>
                                        </h3>
                                        
                                        <button
                                            onClick={toggleAchievementsGallery}
                                            className={`p-2 rounded-lg ${
                                                isDarkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-100'
                                            }`}
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                                
                                <div className="p-6 overflow-y-auto max-h-[70vh]">
                                    {achievements.length === 0 ? (
                                        <div className="text-center py-12">
                                            <div className="text-6xl mb-4">🏆</div>
                                            <p className={`text-lg ${
                                                isDarkMode ? 'text-gray-300' : 'text-gray-600'
                                            }`}>
                                                ¡Los logros se cargarán cuando completes tu primer puzzle!
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {achievements.map((achievement, index) => {
                                                const isCompleted = achievement.is_completed;
                                                const isLocked = !isCompleted;
                                                
                                                return (
                                                    <div
                                                        key={achievement.id || index}
                                                        className={`p-4 rounded-lg border transition-all ${
                                                            isCompleted
                                                                ? isDarkMode 
                                                                    ? 'bg-yellow-900 border-yellow-600 shadow-lg' 
                                                                    : 'bg-yellow-50 border-yellow-300 shadow-lg'
                                                                : isDarkMode 
                                                                    ? 'bg-gray-700 border-gray-600 opacity-60' 
                                                                    : 'bg-gray-50 border-gray-300 opacity-60'
                                                        }`}
                                                    >
                                                        <div className="flex items-start gap-3">
                                                            <div className={`text-2xl ${
                                                                isLocked ? 'grayscale opacity-50' : ''
                                                            }`}>
                                                                {isLocked ? '🔒' : achievement.icon}
                                                            </div>
                                                            
                                                            <div className="flex-1">
                                                                <h4 className={`font-semibold ${
                                                                    isCompleted 
                                                                        ? isDarkMode ? 'text-yellow-200' : 'text-yellow-800'
                                                                        : isDarkMode ? 'text-gray-300' : 'text-gray-600'
                                                                }`}>
                                                                    {isLocked ? '???' : achievement.name}
                                                                </h4>
                                                                
                                                                <p className={`text-sm mt-1 ${
                                                                    isCompleted 
                                                                        ? isDarkMode ? 'text-yellow-300' : 'text-yellow-700'
                                                                        : isDarkMode ? 'text-gray-400' : 'text-gray-500'
                                                                }`}>
                                                                    {isLocked ? 'Logro bloqueado - completa puzzles para desbloquearlo' : achievement.description}
                                                                </p>
                                                                
                                                                {isCompleted && achievement.unlocked_at && (
                                                                    <p className={`text-xs mt-2 ${
                                                                        isDarkMode ? 'text-yellow-400' : 'text-yellow-600'
                                                                    }`}>
                                                                        ✨ Desbloqueado el {new Date(achievement.unlocked_at).toLocaleDateString()}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            
                                                            {isCompleted && (
                                                                <div className="text-green-500 text-xl">
                                                                    ✓
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                    
                                    {achievements.length > 0 && (
                                        <div className={`mt-6 p-4 rounded-lg border ${
                                            isDarkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-300'
                                        }`}>
                                            <h4 className="font-semibold mb-2">📊 Progreso General</h4>
                                            
                                            <div className={`w-full bg-gray-300 rounded-full h-3 mb-3 ${
                                                isDarkMode ? 'bg-gray-600' : 'bg-gray-300'
                                            }`}>
                                                <div 
                                                    className="bg-yellow-500 h-3 rounded-full transition-all duration-500"
                                                    style={{
                                                        width: `${(achievements.filter(a => a.is_completed).length / achievements.length) * 100}%`
                                                    }}
                                                ></div>
                                            </div>
                                            
                                            <div className="grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span className="font-medium">Completados:</span>
                                                    <span className="ml-2 font-mono">
                                                        {achievements.filter(a => a.is_completed).length}/{achievements.length}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="font-medium">Progreso:</span>
                                                    <span className="ml-2 font-mono">
                                                        {Math.round((achievements.filter(a => a.is_completed).length / achievements.length) * 100)}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
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
                                        
                                        {/* 💾 INDICADOR DE AUTO-GUARDADO */}
                                        {autoSaveStatus !== 'idle' && (
                                            <span className={`text-xs px-2 py-1 rounded-md font-medium ${
                                                autoSaveStatus === 'saving' 
                                                    ? isDarkMode ? 'bg-yellow-800 text-yellow-200' : 'bg-yellow-100 text-yellow-800'
                                                    : autoSaveStatus === 'saved'
                                                        ? isDarkMode ? 'bg-green-800 text-green-200' : 'bg-green-100 text-green-800'
                                                        : isDarkMode ? 'bg-red-800 text-red-200' : 'bg-red-100 text-red-800'
                                            }`}>
                                                {autoSaveStatus === 'saving' && '💾 Guardando...'}
                                                {autoSaveStatus === 'saved' && '✅ Guardado'}
                                                {autoSaveStatus === 'error' && '❌ Error'}
                                            </span>
                                        )}
                                        
                                        {lastSaved && autoSaveStatus === 'idle' && (
                                            <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                                                💾 {formatTimeSince(lastSaved)}
                                            </span>
                                        )}
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
                                    
                                    {/* 🏆 BOTÓN DE LOGROS */}
                                    <button
                                        onClick={toggleAchievementsGallery}
                                        className={`px-3 py-1 rounded-md text-sm font-medium transition-colors relative ${
                                            isDarkMode 
                                                ? 'bg-yellow-600 hover:bg-yellow-700 text-white' 
                                                : 'bg-yellow-500 hover:bg-yellow-600 text-white'
                                        }`}
                                        title="Ver logros"
                                    >
                                        🏆 Logros
                                        {achievements.filter(a => a.is_completed).length > 0 && (
                                            <span className={`absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs flex items-center justify-center font-bold ${
                                                isDarkMode ? 'bg-yellow-400 text-yellow-900' : 'bg-yellow-300 text-yellow-800'
                                            }`}>
                                                {achievements.filter(a => a.is_completed).length}
                                            </span>
                                        )}
                                    </button>
                                    
                                    {/* 🎵 BOTÓN DE SONIDO */}
                                    <button
                                        onClick={toggleSound}
                                        className={`p-2 rounded-md transition-colors ${
                                            soundEnabled
                                                ? isDarkMode 
                                                    ? 'bg-green-600 hover:bg-green-700 text-white' 
                                                    : 'bg-green-500 hover:bg-green-600 text-white'
                                                : isDarkMode 
                                                    ? 'bg-gray-600 hover:bg-gray-700 text-white' 
                                                    : 'bg-gray-400 hover:bg-gray-500 text-white'
                                        }`}
                                        title={soundEnabled ? 'Silenciar sonidos' : 'Activar sonidos'}
                                    >
                                        {soundEnabled ? '🔊' : '🔇'}
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
                                            
                                            // 🎵 SONIDO ANTES DE VERIFICAR
                                            if (canErase) {
                                                playSound.click();
                                            }
                                            
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
                                    
                                    {/* 💡 BOTÓN DE PISTAS */}
                                    <button
                                        onClick={getHint}
                                        disabled={hintsRemaining <= 0 || puzzleCompleted}
                                        className={`
                                            w-full h-12 rounded-lg font-semibold number-button mt-2
                                            ${hintsRemaining <= 0 || puzzleCompleted
                                                ? isDarkMode 
                                                    ? 'bg-gray-700 text-gray-500 cursor-not-allowed' 
                                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                                : isDarkMode 
                                                    ? 'bg-yellow-600 text-white hover:bg-yellow-700' 
                                                    : 'bg-yellow-500 text-white hover:bg-yellow-600'
                                            }
                                        `}
                                    >
                                        💡 Pista ({hintsRemaining}/3)
                                    </button>
                                    
                                    {/* 💾 BOTÓN DE GUARDADO MANUAL */}
                                    <button
                                        onClick={saveGameManually}
                                        disabled={!gameId || puzzleCompleted || autoSaveStatus === 'saving'}
                                        className={`
                                            w-full h-10 rounded-lg font-semibold text-sm number-button mt-2
                                            ${!gameId || puzzleCompleted || autoSaveStatus === 'saving'
                                                ? isDarkMode 
                                                    ? 'bg-gray-700 text-gray-500 cursor-not-allowed' 
                                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                                : isDarkMode 
                                                    ? 'bg-purple-600 text-white hover:bg-purple-700' 
                                                    : 'bg-purple-500 text-white hover:bg-purple-600'
                                            }
                                        `}
                                    >
                                        {autoSaveStatus === 'saving' ? '💾 Guardando...' : 
                                         autoSaveStatus === 'saved' ? '✅ Guardado' :
                                         autoSaveStatus === 'error' ? '❌ Reintentar' :
                                         '💾 Guardar ahora'}
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
                                            <div className="flex justify-between">
                                                <span>Pistas usadas:</span>
                                                <span className={`font-mono ${(3 - hintsRemaining) > 0 ? 'text-yellow-600' : ''}`}>
                                                    {3 - hintsRemaining}/3
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* 🎵 CONTROLES DE SONIDO */}
                                    <div className={`mt-4 p-3 rounded-lg ${
                                        isDarkMode ? 'bg-gray-700' : 'bg-gray-50'
                                    }`}>
                                        <h4 className="font-medium mb-2 flex items-center gap-2">
                                            🎵 Sonido
                                            <button
                                                onClick={toggleSound}
                                                className={`text-xs px-2 py-1 rounded ${
                                                    soundEnabled
                                                        ? isDarkMode ? 'bg-green-700 text-green-200' : 'bg-green-100 text-green-800'
                                                        : isDarkMode ? 'bg-gray-600 text-gray-300' : 'bg-gray-200 text-gray-600'
                                                }`}
                                            >
                                                {soundEnabled ? '🔊 ON' : '🔇 OFF'}
                                            </button>
                                        </h4>
                                        
                                        {soundEnabled && (
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2 text-sm">
                                                    <span>Volumen:</span>
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="1"
                                                        step="0.1"
                                                        value={soundVolume}
                                                        onChange={(e) => {
                                                            const newVolume = parseFloat(e.target.value);
                                                            setSoundVolume(newVolume);
                                                            // Reproducir sonido de prueba
                                                            setTimeout(() => playSound.click(), 100);
                                                        }}
                                                        className="flex-1 h-2"
                                                    />
                                                    <span className="font-mono text-xs w-8">
                                                        {Math.round(soundVolume * 100)}%
                                                    </span>
                                                </div>
                                                
                                                <div className="flex gap-1 text-xs">
                                                    <button
                                                        onClick={() => playSound.place()}
                                                        className={`px-2 py-1 rounded ${
                                                            isDarkMode ? 'bg-blue-700 text-blue-200 hover:bg-blue-600' : 'bg-blue-100 text-blue-800 hover:bg-blue-200'
                                                        }`}
                                                    >
                                                        🔢 Colocar
                                                    </button>
                                                    <button
                                                        onClick={() => playSound.hint()}
                                                        className={`px-2 py-1 rounded ${
                                                            isDarkMode ? 'bg-yellow-700 text-yellow-200 hover:bg-yellow-600' : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
                                                        }`}
                                                    >
                                                        💡 Pista
                                                    </button>
                                                    <button
                                                        onClick={() => playSound.success()}
                                                        className={`px-2 py-1 rounded ${
                                                            isDarkMode ? 'bg-green-700 text-green-200 hover:bg-green-600' : 'bg-green-100 text-green-800 hover:bg-green-200'
                                                        }`}
                                                    >
                                                        🎉 Éxito
                                                    </button>
                                                </div>
                                            </div>
                                        )}
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